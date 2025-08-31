<?php

namespace Knuckles\Scribe\Extracting;

use Closure;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ClosureValidationRule;
use Knuckles\Scribe\Exceptions\CouldntProcessValidationRule;
use Knuckles\Scribe\Exceptions\ProblemParsingValidationRules;
use Knuckles\Scribe\Exceptions\ScribeException;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\WritingUtils as w;
use ReflectionClass;
use ReflectionFunction;
use Throwable;

trait ParsesValidationRules
{
    use ParamHelpers;

    public static \stdClass $MISSING_VALUE;

    public function getParametersFromValidationRules(array $validationRulesByParameters, array $customParameterData = []): array
    {
        self::$MISSING_VALUE = new \stdClass();
        $validationRulesByParameters = $this->normaliseRules($validationRulesByParameters);

        $parameters = [];
        $rulesWhichDependOnType = ['between', 'max', 'min', 'size', 'gt', 'gte', 'lt', 'lte', 'before', 'after', 'before_or_equal', 'after_or_equal'];
        foreach ($validationRulesByParameters as $parameter => $ruleset) {
            $userSpecifiedParameterInfo = $customParameterData[$parameter] ?? [];
            $stringRules = array_filter($ruleset, fn($rule) => is_string($rule));
            $rulesAndArguments = array_map(fn($rule) => $this->parseStringRuleIntoRuleAndArguments($rule), $stringRules);

            try {
                $this->warnAboutMissingCustomParameterData($parameter, $customParameterData);

                // Make sure the user-specified description comes first (and add full stops where needed).
                $description = $userSpecifiedParameterInfo['description'] ?? '';
                if (!empty($description) && !Str::endsWith($description, '.')) {
                    $description .= '.';
                }
                $parameterData = [
                    'name' => $parameter,
                    'required' => false,
                    'type' => null,
                    'example' => self::$MISSING_VALUE,
                    'description' => $description,
                    'nullable' => false,
                ];

                $closureRules = array_filter($ruleset, fn($rule) => ($rule instanceof ClosureValidationRule || $rule instanceof Closure));
                foreach ($closureRules as $rule) {
                    $this->processClosureRule($rule, $parameterData);
                }

                $enumValidationRules = array_filter($ruleset, fn($rule) => $rule instanceof \Illuminate\Validation\Rules\Enum);
                foreach ($enumValidationRules as $rule) {
                    $this->processEnumValidationRule($rule, $parameterData);
                }

                $ruleObjects = array_filter($ruleset, fn($rule) => ($rule instanceof Rule || $rule instanceof ValidationRule));
                foreach ($ruleObjects as $rule) {
                    $this->processRuleObject($rule, $parameterData);
                }

                // TODO support more rules
                // Process in 3 passes
                // 1. Rules which provide no info about type or example
                // (required, required_*, same, different, nullable, exists, and others in the "utilities" group)
                // 2. Rules which set a type.
                // 3. Rules whose processing depends on the type. ('between', 'max', 'min', 'size', 'gt', 'gte', 'lt', 'lte')
                // - Note: 'in' does not provide type info (does it?), but is enough to generate an example

                // First pass: process rules which provide no type or example info
                $firstPassRuleNames = [
                    "required",
                    "required_*",
                    "same",
                    "different",
                    "nullable",
                ];

                $firstPassRules = array_filter($rulesAndArguments, fn($ruleAndArgs) => Str::is($firstPassRuleNames, $ruleAndArgs[0]));
                foreach ($firstPassRules as $ruleAndArgs) {
                    $this->processRule($ruleAndArgs[0], $ruleAndArgs[1], $parameterData, $validationRulesByParameters);
                }

                $secondPassRules = array_filter($rulesAndArguments, fn($ruleAndArgs) => !Str::is($firstPassRuleNames, $ruleAndArgs[0]) && !in_array($ruleAndArgs[0], $rulesWhichDependOnType));
                foreach ($secondPassRules as $ruleAndArgs) {
                    $this->processRule($ruleAndArgs[0], $ruleAndArgs[1], $parameterData, $validationRulesByParameters);
                }

                // The second pass should have set a type. If not, set a default type
                if (is_null($parameterData['type'])) {
                    $parameterData['type'] = 'string';
                }

                if ($parameterData['required'] === true) {
                    $parameterData['nullable'] = false;
                }

                // Now parse any "dependent" rules and set examples. At this point, we should know all field's types.
                $thirdPassRules = array_filter($rulesAndArguments, fn($ruleAndArgs) => in_array($ruleAndArgs[0], $rulesWhichDependOnType));
                foreach ($thirdPassRules as $ruleAndArgs) {
                    $this->processRule($ruleAndArgs[0], $ruleAndArgs[1], $parameterData, $validationRulesByParameters);
                }

                // Make sure the user-specified example overwrites ours.
                if (array_key_exists('example', $userSpecifiedParameterInfo)) {
                    if ($userSpecifiedParameterInfo['example'] != null && $this->shouldCastUserExample()) {
                        // Examples in comments are strings, we need to cast them properly
                        $parameterData['example'] = $this->castToType($userSpecifiedParameterInfo['example'], $parameterData['type'] ?? 'string');
                    } else {
                        $parameterData['example'] = $userSpecifiedParameterInfo['example'];
                    }
                }

                // End descriptions with a full stop
                if (!empty($parameterData['description']) && !Str::endsWith($parameterData['description'], '.')) {
                    $parameterData['description'] .= '.';
                }

                $parameterData['description'] = trim($parameterData['description']);
                $parameters[$parameter] = $parameterData;
            } catch (Throwable $e) {
                if ($e instanceof ScribeException) {
                    // This is a lower-level error that we've encountered and wrapped;
                    // Pass it on to the user.
                    throw $e;
                }
                throw ProblemParsingValidationRules::forParam($parameter, $e);
            }
        }

        return $parameters;
    }

    /**
     * Transform validation rules:
     * 1. from strings to arrays:
     * 'param1' => 'int|required'  TO  'param1' => ['int', 'required']
     * 2. from '*.foo' to '
     *
     * @param array<string,string|string[]> $rules
     *
     * @return array
     */
    protected function normaliseRules(array $rules): array
    {
        // We can simply call Validator::make($data, $rules)->getRules() to get the normalised rules,
        // but Laravel will ignore any nested array rules (`ids.*')
        // unless the key referenced (`ids`) exists in the dataset and is a non-empty array
        // So we'll create a single-item array for each array parameter
        $testData = [];
        foreach ($rules as $key => $ruleset) {
            if (!Str::contains($key, '.*')) continue;

            // All we need is for Laravel to see this key exists
            Arr::set($testData, str_replace('.*', '.0', $key), Str::random());
        }

        // Now this will return the complete ruleset.
        // Nested array parameters will be present, with '*' replaced by '0'
        $newRules = Validator::make($testData, $rules)->getRules();

        return collect($newRules)->mapWithKeys(function ($val, $paramName) use ($rules) {
            // Transform the key names back from '__asterisk__' to '*'
            if (Str::contains($paramName, '__asterisk__')) {
                // In Laravel < v11.44, * keys were replaced with only "__asterisk__"
                // After that, * keys were replaced with "__asterisk__<random placeholder>", eg "__asterisk__dkjiu78gujjhb
                // See https://github.com/laravel/framework/pull/54845/
                $paramName = preg_replace('/__asterisk__[^.]*\b/', '*', $paramName);
            }

            // Transform the key names back from 'ids.0' to 'ids.*'
            if (Str::contains($paramName, '.0')) {
                $genericArrayKeyName = str_replace('.0', '.*', $paramName);

                // But only if that was the original value
                if (isset($rules[$genericArrayKeyName])) {
                    $paramName = $genericArrayKeyName;
                }
            }

            return [$paramName => $val];
        })->toArray();
    }

    // For inline Closure rules, turn any comment above it into a description
    //
    // $request->validate([
    // 'my_param' => [
    // 'required',
    //     /** Must be a hexadecimal number. */
    //     function ($attribute, $value, $fail) {
    //         if (!preg_match('/^[0-9a-f]+$/', $value)) {
    //             $fail('Must be in hex format');
    //         }
    //     },
    // ],
    // ]);
    protected function processClosureRule($rule, array &$parameterData): void
    {
        $docComment = (new ReflectionFunction($rule instanceof ClosureValidationRule ? $rule->callback : $rule))
            ->getDocComment();

        if (is_string($docComment)) {
            $description = '';
            foreach (explode("\n", $docComment) as $line) {
                $cleaned = preg_replace(['/\*+\/$/', '/^\/\*+\s*/', '/^\*+\s*/'], '', trim($line));
                if ($cleaned != '') $description .= ' ' . $cleaned;
            }

            $parameterData['description'] .= $description;
        }
    }

    protected function processEnumValidationRule($rule, array &$parameterData, array $allParameters = []): void
    {
        $property = (new ReflectionClass($rule))->getProperty('type');
        $property->setAccessible(true);
        $enumClass = $property->getValue($rule);

        if (enum_exists($enumClass) && method_exists($enumClass, 'tryFrom')) {
            // $case->value only exists on BackedEnums, not UnitEnums
            // method_exists($enum, 'tryFrom') implies the enum is a BackedEnum
            // @phpstan-ignore-next-line
            $cases = array_map(fn($case) => $case->value, $enumClass::cases());
            $parameterData['type'] = gettype($cases[0]);
            $parameterData['enumValues'] = $cases;
            $parameterData['setter'] = fn() => Arr::random($cases);
        }
    }

    protected function processRuleObject($rule, array &$parameterData): void
    {
        if (method_exists($rule, 'invokable')) {
            // Laravel wraps InvokableRule instances in an InvokableValidationRule class,
            // so we must retrieve the original rule
            $rule = $rule->invokable();
        }

        // Users can define a custom "docs" method on a rule to give Scribe more info.
        if (method_exists($rule, 'docs')) {
            $customData = call_user_func_array([$rule, 'docs'], []) ?: [];

            if (isset($customData['description'])) {
                $parameterData['description'] .= ' ' . $customData['description'];
            }
            if (isset($customData['example'])) {
                $parameterData['setter'] = fn() => $customData['example'];
            } elseif (isset($customData['setter'])) {
                $parameterData['setter'] = $customData['setter'];
            }

            $parameterData = array_merge($parameterData, Arr::except($customData, [
                'description', 'example', 'setter',
            ]));
        }
    }

    /**
     * Parse a validation rule and extract a parameter type, description and setter (used to generate an example).
     * @param array $allParameters All parameters, used to check if an argument in the date rules (eg `before:some_date`)
     *   is a parameter in the request body.
     */
    protected function processRule($rule, $ruleArguments, array &$parameterData, array $allParameters = []): bool
    {
        // Reminder: Always append to the description (with a leading space); don't overwrite.
        try {
            switch ($rule) {
                case 'required':
                    $parameterData['required'] = true;
                    break;
                case 'accepted':
                    $parameterData['required'] = true;
                    $parameterData['type'] = 'boolean';
                    $parameterData['description'] .= ' Must be accepted.';
                    $parameterData['setter'] = fn() => true;
                    break;
                case 'accepted_if':
                    $parameterData['type'] = 'boolean';
                    $parameterData['description'] .= " Must be accepted when <code>$ruleArguments[0]</code> is " . w::getListOfValuesAsFriendlyHtmlString(array_slice($ruleArguments, 1));
                    $parameterData['setter'] = fn() => true;
                    break;

                /*
                * Primitive types. No description should be added
                */
                case 'bool':
                case 'boolean':
                    $parameterData['setter'] = function () {
                        return Arr::random([true, false]);
                    };
                    $parameterData['type'] = 'boolean';
                    break;
                case 'string':
                    $parameterData['setter'] = function () use ($parameterData) {
                        return $this->generateDummyValue('string', ['name' => $parameterData['name']]);
                    };
                    $parameterData['type'] = 'string';
                    break;
                case 'int':
                case 'integer':
                    $parameterData['setter'] = function () {
                        return $this->generateDummyValue('integer');
                    };
                    $parameterData['type'] = 'integer';
                    break;
                case 'numeric':
                    $parameterData['setter'] = function () {
                        return $this->generateDummyValue('number');
                    };
                    $parameterData['type'] = 'number';
                    break;
                case 'array':
                    $parameterData['setter'] = function () {
                        return [$this->generateDummyValue('string')];
                    };
                    $parameterData['type'] = 'array'; // The cleanup code in normaliseArrayAndObjectParameters() will set this to a valid type (x[] or object)
                    break;
                case 'file':
                    $parameterData['type'] = 'file';
                    $parameterData['description'] .= ' Must be a file.';
                    $parameterData['setter'] = function () {
                        return $this->generateDummyValue('file');
                    };
                    break;

                /**
                 * Special string types
                 */
                case 'alpha':
                    $parameterData['description'] .= " Must contain only letters.";
                    $parameterData['setter'] = function () {
                        return $this->getFaker()->lexify('??????');
                    };
                    break;
                case 'alpha_dash':
                    $parameterData['description'] .= " Must contain only letters, numbers, dashes and underscores.";
                    $parameterData['setter'] = function () {
                        return $this->getFaker()->lexify('???-???_?');
                    };
                    break;
                case 'alpha_num':
                    $parameterData['description'] .= " Must contain only letters and numbers.";
                    $parameterData['setter'] = function () {
                        return $this->getFaker()->bothify('#?#???#');
                    };
                    break;
                case 'timezone':
                    // Laravel's message merely says "The value must be a valid zone"
                    $parameterData['description'] .= " Must be a valid time zone, such as <code>Africa/Accra</code>.";
                    $parameterData['setter'] = $this->getFakeFactoryByName('timezone');
                    break;
                case 'email':
                    $parameterData['description'] .= ' ' . $this->getDescription($rule);
                    $parameterData['setter'] = $this->getFakeFactoryByName('email');
                    $parameterData['type'] = 'string';
                    break;
                case 'url':
                    $parameterData['setter'] = $this->getFakeFactoryByName('url');
                    $parameterData['type'] = 'string';
                    // Laravel's message is "The value format is invalid". Ugh.🤮
                    $parameterData['description'] .= " Must be a valid URL.";
                    break;
                case 'ip':
                    $parameterData['description'] .= ' ' . $this->getDescription($rule);
                    $parameterData['type'] = 'string';
                    $parameterData['setter'] = function () {
                        return $this->getFaker()->ipv4();
                    };
                    break;
                case 'json':
                    $parameterData['type'] = 'string';
                    $parameterData['description'] .= ' ' . $this->getDescription($rule);
                    $parameterData['setter'] = function () {
                        return json_encode([$this->getFaker()->word(), $this->getFaker()->word(),]);
                    };
                    break;
                case 'date':
                    $parameterData['type'] = 'string';
                    $parameterData['description'] .= ' ' . $this->getDescription($rule);
                    $parameterData['setter'] = fn() => date('Y-m-d\TH:i:s', time());
                    break;
                case 'date_format':
                    $parameterData['type'] = 'string';
                    // Laravel description here is "The value must match the format Y-m-d". Not descriptive enough.
                    $parameterData['description'] .= " Must be a valid date in the format <code>{$ruleArguments[0]}</code>.";
                    $parameterData['setter'] = function () use ($ruleArguments) {
                        return date($ruleArguments[0], time());
                    };
                    break;
                case 'after':
                case 'after_or_equal':
                    $parameterData['type'] = 'string';
                    $parameterData['description'] .= ' ' . $this->getDescription($rule, [':date' => "<code>{$ruleArguments[0]}</code>"]);
                    // TODO introduce the concept of "modifiers", like date_format
                    // The startDate may refer to another field, in which case, we just ignore it for now.
                    $startDate =  array_key_exists($ruleArguments[0], $allParameters) ? 'today' : $ruleArguments[0];
                    $parameterData['setter'] = fn() => $this->getFaker()->dateTimeBetween($startDate, '+100 years')->format('Y-m-d');
                    break;
                case 'before':
                case 'before_or_equal':
                    $parameterData['type'] = 'string';
                    // The argument can be either another field or a date
                    // The endDate may refer to another field, in which case, we just ignore it for now.
                    $endDate = array_key_exists($ruleArguments[0], $allParameters) ? 'today' : $ruleArguments[0];
                    $parameterData['description'] .= ' ' . $this->getDescription($rule, [':date' => "<code>{$ruleArguments[0]}</code>"]);
                    $parameterData['setter'] = fn() => $this->getFaker()->dateTimeBetween('-30 years', $endDate)->format('Y-m-d');
                    break;
                case 'starts_with':
                    $parameterData['description'] .= ' Must start with one of ' . w::getListOfValuesAsFriendlyHtmlString($ruleArguments);
                    $parameterData['setter'] = fn() => $this->getFaker()->lexify("{$ruleArguments[0]}????");;
                    break;
                case 'ends_with':
                    $parameterData['description'] .= ' Must end with one of ' . w::getListOfValuesAsFriendlyHtmlString($ruleArguments);
                    $parameterData['setter'] = fn() => $this->getFaker()->lexify("????{$ruleArguments[0]}");;
                    break;
                case 'uuid':
                    $parameterData['description'] .= ' ' . $this->getDescription($rule) . ' ';
                    $parameterData['setter'] = $this->getFakeFactoryByName('uuid');
                    break;
                case 'regex':
                    $parameterData['description'] .= ' ' . $this->getDescription($rule, [':regex' => $ruleArguments[0]]);
                    $parameterData['setter'] = fn() => $this->getFaker()->regexify($ruleArguments[0]);;
                    break;

                /**
                 * Special number types.
                 */
                case 'digits':
                    $parameterData['description'] .= ' ' . $this->getDescription($rule, [':digits' => $ruleArguments[0]]);
                    $parameterData['setter'] = fn() => $this->getFaker()->numerify(str_repeat("#", $ruleArguments[0]));
                    $parameterData['type'] = 'string';
                    break;
                case 'digits_between':
                    $parameterData['description'] .= ' ' . $this->getDescription($rule, [':min' => $ruleArguments[0], ':max' => $ruleArguments[1]]);
                    $parameterData['setter'] = fn() => $this->getFaker()->numerify(str_repeat("#", rand($ruleArguments[0], $ruleArguments[1])));
                    $parameterData['type'] = 'string';
                    break;

                /**
                 * These rules can apply to numbers, strings, arrays or files
                 */
                case 'size':
                    $parameterData['description'] .= ' ' . $this->getDescription(
                            $rule, [':size' => $ruleArguments[0]], $this->getLaravelValidationBaseTypeMapping($parameterData['type'])
                        );
                    $parameterData['setter'] = $this->getDummyValueGenerator($parameterData['type'], ['size' => $ruleArguments[0]]);
                    break;
                case 'min':
                    $parameterData['description'] .= ' ' . $this->getDescription(
                            $rule, [':min' => $ruleArguments[0]], $this->getLaravelValidationBaseTypeMapping($parameterData['type'])
                        );
                    $parameterData['setter'] = $this->getDummyDataGeneratorBetween($parameterData['type'], floatval($ruleArguments[0]), fieldName: $parameterData['name']);
                    break;
                case 'max':
                    $parameterData['description'] .= ' ' . $this->getDescription(
                            $rule, [':max' => $ruleArguments[0]], $this->getLaravelValidationBaseTypeMapping($parameterData['type'])
                        );
                    $max = min($ruleArguments[0], 25);
                    $parameterData['setter'] = $this->getDummyDataGeneratorBetween($parameterData['type'], 1, $max, $parameterData['name']);
                    break;
                case 'between':
                    $parameterData['description'] .= ' ' . $this->getDescription(
                            $rule, [':min' => $ruleArguments[0], ':max' => $ruleArguments[1]], $this->getLaravelValidationBaseTypeMapping($parameterData['type'])
                        );
                    // Avoid exponentially complex operations by using the minimum length
                    $parameterData['setter'] = $this->getDummyDataGeneratorBetween($parameterData['type'], floatval($ruleArguments[0]), floatval($ruleArguments[0]) + 1, $parameterData['name']);
                    break;

                /**
                 * Special file types.
                 */
                case 'image':
                    $parameterData['type'] = 'file';
                    $parameterData['description'] .= ' ' . $this->getDescription($rule) . ' ';
                    $parameterData['setter'] = function () {
                        // This is fine because the file example generator generates an image
                        return $this->generateDummyValue('file');
                    };
                    break;

                /**
                 * Other rules.
                 */
                case 'in':
                    $parameterData['enumValues'] = $ruleArguments;
                    $parameterData['setter'] = function () use ($ruleArguments) {
                        return Arr::random($ruleArguments);
                    };
                    break;

                /**
                 * These rules only add a description. Generating valid examples is too complex.
                 */
                case 'not_in':
                    $parameterData['description'] .= ' Must not be one of ' . w::getListOfValuesAsFriendlyHtmlString($ruleArguments) . ' ';
                    break;
                case 'required_if':
                    $parameterData['description'] .= sprintf(
                        " This field is required when <code>{$ruleArguments[0]}</code> is %s. ",
                        w::getListOfValuesAsFriendlyHtmlString(array_slice($ruleArguments, 1))
                    );
                    break;
                case 'required_unless':
                    $parameterData['description'] .= sprintf(
                        " This field is required unless <code>{$ruleArguments[0]}</code> is in %s. ",
                        w::getListOfValuesAsFriendlyHtmlString(array_slice($ruleArguments, 1))
                    );
                    break;
                case 'required_with':
                    $parameterData['description'] .= sprintf(
                        " This field is required when %s is present. ",
                        w::getListOfValuesAsFriendlyHtmlString($ruleArguments)
                    );
                    break;
                case 'required_without':
                    $parameterData['description'] .= sprintf(
                        " This field is required when %s is not present. ",
                        w::getListOfValuesAsFriendlyHtmlString($ruleArguments)
                    );
                    break;
                case 'required_with_all':
                    $parameterData['description'] .= sprintf(
                        " This field is required when %s are present. ",
                        w::getListOfValuesAsFriendlyHtmlString($ruleArguments, "and")
                    );
                    break;
                case 'required_without_all':
                    $parameterData['description'] .= sprintf(
                        " This field is required when none of %s are present. ",
                        w::getListOfValuesAsFriendlyHtmlString($ruleArguments, "and")
                    );
                    break;
                case 'same':
                    $parameterData['description'] .= " The value and <code>{$ruleArguments[0]}</code> must match.";
                    break;
                case 'different':
                    $parameterData['description'] .= " The value and <code>{$ruleArguments[0]}</code> must be different.";
                    break;
                case 'nullable':
                    $parameterData['nullable'] = true;
                    break;
                case 'exists':
                    $parameterData['description'] .= " The <code>{$ruleArguments[1]}</code> of an existing record in the {$ruleArguments[0]} table.";
                    break;
                default:
                    // Other rules not supported
                    break;
            }
        } catch (Throwable $e) {
            throw CouldntProcessValidationRule::forParam($parameterData['name'], $rule, $e);
        }

        $parameterData['description'] = trim($parameterData['description']);
        return true;
    }

    /**
     * Parse a string rule into the base rule and arguments.
     * eg "in:1,2" becomes ["in", ["1", "2"]]
     * Laravel validation rules are specified in the format {rule}:{arguments}
     * Arguments are separated by commas.
     * For instance the rule "max:3" states that the value may only be three letters.
     *
     * @param string|Rule $rule
     *
     * @return array
     */
    protected function parseStringRuleIntoRuleAndArguments($rule): array
    {
        $ruleArguments = [];

        if (str_contains($rule, ':')) {
            [$rule, $argumentsString] = explode(':', $rule, 2);

            // These rules can have commas in their arguments, so we don't split on commas
            if (in_array(strtolower($rule), ['regex', 'date', 'date_format'])) {
                $ruleArguments = [$argumentsString];
            } else {
                $ruleArguments = str_getcsv($argumentsString);
            }
        }

        return [strtolower(trim($rule)), $ruleArguments];
    }

    protected function getParameterExample(array $parameterData)
    {
        // If no example was given by the user, set an autogenerated example.
        // Each parsed rule returns a 'setter' function. We'll evaluate the last one.
        if ($parameterData['example'] === self::$MISSING_VALUE) {
            if (isset($parameterData['setter'])) {
                return $parameterData['setter']();
            } else {
                return $parameterData['required']
                    ? $this->generateDummyValue($parameterData['type'])
                    : null;
            }
        } else if (!is_null($parameterData['example']) && $parameterData['example'] !== self::$MISSING_VALUE) {
            if ($parameterData['example'] === 'No-example' && !$parameterData['required']) {
                return null;
            }
            // Casting again is important since values may have been cast to string in the validator
            return $this->castToType($parameterData['example'], $parameterData['type']);
        }

        return $parameterData['example'] === self::$MISSING_VALUE ? null : $parameterData['example'];
    }

    /**
     * Laravel uses .* notation for arrays. This PR aims to normalise that into our "new syntax".
     *
     * 'years.*' with type 'integer' becomes 'years' with type 'integer[]'
     * 'cars.*.age' with type 'string' becomes 'cars[].age' with type 'string' and 'cars' with type 'object[]'
     * 'cars.*.things.*.*' with type 'string' becomes 'cars[].things' with type 'string[][]' and 'cars' with type
     * 'object[]'
     *
     * Additionally, if the user declared a subfield but not the parent, we create a parameter for the parent.
     *
     * @param array[] $parametersFromValidationRules
     *
     * @return array
     */
    public function normaliseArrayAndObjectParameters(array $parametersFromValidationRules): array
    {
        // Convert any `array` types into concrete types like `object[]`, object, or `string[]`
        $parameters = $this->convertGenericArrayType($parametersFromValidationRules);

        // Change cars.*.dogs.things.*.* with type X to cars.*.dogs.things with type X[][]
        $parameters = $this->convertArraySubfields($parameters);

        // Add the fields `cars.*.dogs` and `cars` if they don't exist
        $parameters = $this->addMissingParentFields($parameters);

        return $this->setExamples($parameters);
    }

    public function convertGenericArrayType(array $parameters): array
    {
        $converted = [];
        $allKeys = array_keys($parameters);
        foreach (array_reverse($parameters) as $name => $details) {
            if ($details['type'] === 'array') {
                // This is a parent field, a generic array type. Scribe only supports concrete array types (T[]),
                // so we convert this to the correct type (such as object or object[])

                // Supposing current key is "users", with type "array". To fix this:
                // 1. If `users.*` or `users.*.thing` exists, `users` is an `X[]` (where X is the type of `users.*`
                // 2. If `users.<name>` exists, `users` is an `object`
                // 3. Otherwise, default to `object`
                // Important: We're iterating in reverse, to ensure we set child items before parent items
                // (assuming the user specified parents first, which is the more common thing)y
                if (Arr::first($allKeys, fn($key) => Str::startsWith($key, "$name.*."))) {
                    $details['type'] = 'object[]';
                    unset($details['setter']);
                } else if ($childKey = Arr::first($allKeys, fn($key) => Str::startsWith($key, "$name.*"))) {
                    $childType = ($converted[$childKey] ?? $parameters[$childKey])['type'];
                    $details['type'] = "{$childType}[]";
                } else { // `array` types default to `object` if no subtype is specified
                    $details['type'] = 'object';
                    unset($details['setter']);
                }
            }

            $converted[$name] = $details;
        }

        // Re-add items in the original order, so as to not cause side effects
        foreach ($allKeys as $key) {
            $parameters[$key] = $converted[$key] ?? $parameters[$key];
        }

        return $parameters;
    }

    public function convertArraySubfields(array $parameters): array
    {
        $results = [];
        foreach ($parameters as $name => $details) {
            if (Str::endsWith($name, '.*')) {
                // The user might have set the example via bodyParameters()
                $exampleWasSpecified = $this->examplePresent($details);

                // Change cars.*.dogs.things.*.* with type X to cars.*.dogs.things with type X[][]
                while (Str::endsWith($name, '.*')) {
                    $details['type'] .= '[]';
                    $name = substr($name, 0, -2);

                    if ($exampleWasSpecified) {
                        $details['example'] = [$details['example']];
                    } else if (isset($details['setter'])) {
                        $previousSetter = $details['setter'];
                        $details['setter'] = fn() => [$previousSetter()];
                    }
                }
            }

            $results[$name] = $details;
        }

        return $results;
    }

    public function setExamples(array $parameters): array
    {
        $examples = [];

        foreach ($parameters as $name => $details) {
            if ($this->examplePresent($details)) {
                // Record already-present examples (eg from bodyParameters()).
                // This allows a user to set 'data' => ['example' => ['title' => 'A title'],
                // and we automatically set this as the example for `data.title`
                // Note that this approach assumes parent fields are listed before the children; meh.
                $examples[$details['name']] = $details['example'];
            } elseif (preg_match('/.+\.[^*]+$/', $details['name'])) {
                // For object fields (eg 'data.details.title'), set examples from their parents if present as described above.
                [$parentName, $fieldName] = preg_split('/\.(?=[\w-]+$)/', $details['name']);
                if (array_key_exists($parentName, $examples) && is_array($examples[$parentName])
                    && array_key_exists($fieldName, $examples[$parentName])) {
                    $examples[$details['name']] = $details['example'] = $examples[$parentName][$fieldName];
                }
            }

            $details['example'] = $this->getParameterExample($details);
            unset($details['setter']);

            $parameters[$name] = $details;

        }

        return $parameters;
    }

    protected function addMissingParentFields(array $parameters): array
    {
        $results = [];
        foreach ($parameters as $name => $details) {
            if (isset($results[$name])) {
                continue;
            }

            $parentPath = $name;
            while (Str::contains($parentPath, '.')) {
                $parentPath = preg_replace('/\.[^.]+$/', '', $parentPath);
                $normalisedParentPath = str_replace('.*.', '[].', $parentPath);

                if (empty($results[$normalisedParentPath])) {
                    // Parent field doesn't exist, create it.

                    if (Str::endsWith($parentPath, '.*')) {
                        $parentPath = substr($parentPath, 0, -2);
                        $normalisedParentPath = str_replace('.*.', '[].', $parentPath);

                        if (!empty($results[$normalisedParentPath])) {
                            break;
                        }

                        $type = 'object[]';
                        $example = [[]];
                    } else {
                        $type = 'object';
                        $example = [];
                    }
                    $results[$normalisedParentPath] = [
                        'name' => $normalisedParentPath,
                        'type' => $type,
                        'required' => false,
                        'description' => '',
                        'example' => $example,
                    ];
                }
            }

            $details['name'] = $name = str_replace('.*.', '[].', $name);

            if (isset($parameters[$details['name']]) && $this->examplePresent($parameters[$details['name']])) {
                $details['example'] = $parameters[$details['name']]['example'];
            }

            $results[$name] = $details;
        }

        return $results;
    }

    private function examplePresent(array $parameterData)
    {
        return isset($parameterData['example']) && $parameterData['example'] !== self::$MISSING_VALUE;
    }

    protected function getDescription(string $rule, array $arguments = [], $baseType = 'string'): string
    {
        if ($rule == 'regex') {
            return "Must match the regex {$arguments[':regex']}.";
        }

        $translationString = "validation.{$rule}";
        $description = trans($translationString);

        // For rules that can apply to multiple types (eg 'max' rule), There is an array of possible messages
        // 'numeric' => 'The :attribute must not be greater than :max'
        // 'file' => 'The :attribute must have a size less than :max kilobytes'
        // Depending on the translation engine, trans may return the array, or it will fail to translate the string
        // and will need to be called with the baseType appended.
        if ($description === $translationString) {
            $translationString = "{$translationString}.{$baseType}";
            $translated = trans($translationString);
            if ($translated !== $translationString) {
                $description = $translated;
            }
        } elseif (is_array($description)) {
            $description = $description[$baseType];
        }

        // Convert messages from failure type ("The :attribute is not a valid date.") to info ("The :attribute must be a valid date.")
        $description = str_replace(['is not', 'does not'], ['must be', 'must'], $description);
        $description = str_replace('may not', 'must not', $description);

        foreach ($arguments as $placeholder => $argument) {
            $description = str_replace($placeholder, $argument, $description);
        }

        // Laravel 10 added `field` to its messages: https://github.com/laravel/framework/pull/45974
        $description = str_replace("The :attribute field ", "The value ", $description);

        $description = preg_replace("/(?!<\W):attribute\b/", "value", $description);

        return str_replace(
            ["The value must ", " 1 characters", " 1 digits", " 1 kilobytes"],
            ["Must ", " 1 character", " 1 digit", " 1 kilobyte"],
            $description
        );
    }

    private function getLaravelValidationBaseTypeMapping(string $parameterType): string
    {
        $mapping = [
            'number' => 'numeric',
            'integer' => 'numeric',
            'file' => 'file',
            'string' => 'string',
            'array' => 'array',
        ];

        if (Str::endsWith($parameterType, '[]')) {
            return 'array';
        }

        return $mapping[$parameterType] ?? 'string';
    }

    protected function getMissingCustomDataMessage($parameterName)
    {
        return "";
    }

    protected function shouldCastUserExample()
    {
        return false;
    }

    protected function warnAboutMissingCustomParameterData(string $parameter, array $customParameterData): array
    {
        $parameterPlusDot = $parameter . '.';
        if (count($customParameterData) && !isset($customParameterData[$parameter])
            && !Arr::first(array_keys($customParameterData), fn($key) => str_starts_with($key, $parameterPlusDot))
        ) {
            c::debug($this->getMissingCustomDataMessage($parameter));
        }

        return $customParameterData;
    }
}
