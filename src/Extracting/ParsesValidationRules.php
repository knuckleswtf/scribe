<?php

namespace Knuckles\Scribe\Extracting;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Knuckles\Scribe\Exceptions\CouldntProcessValidationRule;
use Knuckles\Scribe\Exceptions\ProblemParsingValidationRules;
use Knuckles\Scribe\Exceptions\ScribeException;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\WritingUtils as w;
use Throwable;

trait ParsesValidationRules
{
    use ParamHelpers;

    public static \stdClass $MISSING_VALUE;

    public function getParametersFromValidationRules(array $validationRules, array $customParameterData = []): array
    {
        self::$MISSING_VALUE = new \stdClass();
        $validationRules = $this->normaliseRules($validationRules);

        $parameters = [];
        $dependentRules = [];
        foreach ($validationRules as $parameter => $ruleset) {
            try {
                if (count($customParameterData) && !isset($customParameterData[$parameter])) {
                    c::debug($this->getMissingCustomDataMessage($parameter));
                }
                $userSpecifiedParameterInfo = $customParameterData[$parameter] ?? [];

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
                ];
                $dependentRules[$parameter] = [];

                // First, parse only "independent" rules
                foreach ($ruleset as $rule) {
                    $parsed = $this->parseRule($rule, $parameterData, true);
                    if (!$parsed) {
                        $dependentRules[$parameter][] = $rule;
                    }
                }

                $parameterData['description'] = trim($parameterData['description']);

                // Set a default type
                if (is_null($parameterData['type'])) {
                    $parameterData['type'] = 'string';
                }

                $parameterData['name'] = $parameter;
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

        // Now parse any "dependent" rules and set examples. At this point, we should know all field's types.
        foreach ($dependentRules as $parameter => $ruleset) {
            try {
                $parameterData = $parameters[$parameter];
                $userSpecifiedParameterInfo = $customParameterData[$parameter] ?? [];

                foreach ($ruleset as $rule) {
                    $this->parseRule($rule, $parameterData, false, $parameters);
                }

                // Make sure the user-specified example overwrites others.
                if (isset($userSpecifiedParameterInfo['example'])) {
                    if ($this->shouldCastUserExample()) {
                        // Examples in comments are strings, we need to cast them properly
                        $parameterData['example'] = $this->castToType($userSpecifiedParameterInfo['example'], $parameterData['type'] ?? 'string');
                    } else {
                        $parameterData['example'] = $userSpecifiedParameterInfo['example'];
                    }
                }

                // End descriptions with a full stop
                $parameterData['description'] = trim($parameterData['description']);
                if (!empty($parameterData['description']) && !Str::endsWith($parameterData['description'], '.')) {
                    $parameterData['description'] .= '.';
                }

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
     * Transform validation rules from:
     * 'param1' => 'int|required'  TO  'param1' => ['int', 'required']
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
            if (!Str::contains($key, '.*')) {
                continue;
            }

            // All we need is for Laravel to see this key exists
            Arr::set($testData, str_replace('.*', '.0', $key), Str::random());
        }

        // Now this will return the complete ruleset.
        // Nested array parameters will be present, with '*' replaced by '0'
        $newRules = Validator::make($testData, $rules)->getRules();

        // Transform the key names back from 'ids.0' to 'ids.*'
        return collect($newRules)->mapWithKeys(function ($val, $paramName) use ($rules) {
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

    /**
     * Parse a validation rule and extract a parameter type, description and setter (used to generate an example).
     *
     * If $independentOnly is true, only independent rules will be parsed.
     * If a rule depends on another parameter (eg gt:field) or needs to know the type of the parameter first (eg:
     * size:34), it will return false.
     */
    protected function parseRule($rule, array &$parameterData, bool $independentOnly, array $allParameters = []): bool
    {
        try {
            if (!(is_string($rule) || $rule instanceof Rule)) {
                return true;
            }

            // Convert string rules into rule + arguments (eg "in:1,2" becomes ["in", ["1", "2"]])
            $parsedRule = $this->parseStringRuleIntoRuleAndArguments($rule);
            [$rule, $arguments] = $parsedRule;

            $dependentRules = ['between', 'max', 'min', 'size', 'gt', 'gte', 'lt', 'lte', 'before', 'after', 'before_or_equal', 'after_or_equal'];
            if ($independentOnly && in_array($rule, $dependentRules)) {
                return false;
            }

            // Reminders:
            // 1. Append to the description (with a leading space); don't overwrite.
            // 2. Avoid testing on the value of $parameterData['type'],
            // as that may not have been set yet, since the rules can be in any order.
            // For this reason, only deterministic rules are supported
            // 3. All rules supported must be rules that we can generate a valid dummy value for.
            switch ($rule) {
                case 'required':
                    $parameterData['required'] = true;
                    break;
                case 'accepted':
                    $parameterData['required'] = true;
                    $parameterData['type'] = 'boolean';
                    $parameterData['description'] .= ' Must be accepted.';
                    $parameterData['setter'] = fn () => true;
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
                    $parameterData['setter'] = fn () => date('Y-m-d\TH:i:s', time());
                    break;
                case 'date_format':
                    $parameterData['type'] = 'string';
                    // Laravel description here is "The value must match the format Y-m-d". Not descriptive enough.
                    $parameterData['description'] .= " Must be a valid date in the format <code>{$arguments[0]}</code>.";
                    $parameterData['setter'] = function () use ($arguments) {
                        return date($arguments[0], time());
                    };
                    break;
                case 'after':
                case 'after_or_equal':
                    $parameterData['type'] = 'string';
                    $parameterData['description'] .= ' ' . $this->getDescription($rule, [':date' => "<code>{$arguments[0]}</code>"]);
                    // TODO introduce the concept of "modifiers", like date_format
                    // The startDate may refer to another field, in which case, we just ignore it for now.
                    $startDate = isset($allParameters[$arguments[0]]) ? 'today' : $arguments[0];
                    $parameterData['setter'] = fn () => $this->getFaker()->dateTimeBetween($startDate, '+100 years')->format('Y-m-d');
                    break;
                case 'before':
                case 'before_or_equal':
                    $parameterData['type'] = 'string';
                    // The argument can be either another field or a date
                    // The endDate may refer to another field, in which case, we just ignore it for now.
                    $endDate = isset($allParameters[$arguments[0]]) ? 'today' : $arguments[0];
                    $parameterData['description'] .= ' ' . $this->getDescription($rule, [':date' => "<code>{$arguments[0]}</code>"]);
                    $parameterData['setter'] = fn () => $this->getFaker()->dateTimeBetween('-30 years', $endDate)->format('Y-m-d');
                    break;
                case 'starts_with':
                    $parameterData['description'] .= ' Must start with one of ' . w::getListOfValuesAsFriendlyHtmlString($arguments);
                    $parameterData['setter'] = fn () => $this->getFaker()->lexify("{$arguments[0]}????");
                    ;
                    break;
                case 'ends_with':
                    $parameterData['description'] .= ' Must end with one of ' . w::getListOfValuesAsFriendlyHtmlString($arguments);
                    $parameterData['setter'] = fn () => $this->getFaker()->lexify("????{$arguments[0]}");
                    ;
                    break;
                case 'uuid':
                    $parameterData['description'] .= ' ' . $this->getDescription($rule) . ' ';
                    $parameterData['setter'] = $this->getFakeFactoryByName('uuid');
                    break;
                case 'regex':
                    $parameterData['description'] .= ' ' . $this->getDescription($rule, [':regex' => $arguments[0]]);
                    $parameterData['setter'] = fn () => $this->getFaker()->regexify($arguments[0]);
                    ;
                    break;

                    /**
                     * Special number types.
                     */
                case 'digits':
                    $parameterData['description'] .= ' ' . $this->getDescription($rule, [':digits' => $arguments[0]]);
                    $parameterData['setter'] = fn () => $this->getFaker()->numerify(str_repeat("#", $arguments[0]));
                    $parameterData['type'] = 'string';
                    break;
                case 'digits_between':
                    $parameterData['description'] .= ' ' . $this->getDescription($rule, [':min' => $arguments[0], ':max' => $arguments[1]]);
                    $parameterData['setter'] = fn () => $this->getFaker()->numerify(str_repeat("#", rand($arguments[0], $arguments[1])));
                    $parameterData['type'] = 'string';
                    break;

                    /**
                     * These rules can apply to numbers, strings, arrays or files
                     */
                case 'size':
                    $parameterData['description'] .= ' ' . $this->getDescription(
                        $rule,
                        [':size' => $arguments[0]],
                        $this->getLaravelValidationBaseTypeMapping($parameterData['type'])
                    );
                    $parameterData['setter'] = $this->getDummyValueGenerator($parameterData['type'], ['size' => $arguments[0]]);
                    break;
                case 'min':
                    $parameterData['description'] .= ' ' . $this->getDescription(
                        $rule,
                        [':min' => $arguments[0]],
                        $this->getLaravelValidationBaseTypeMapping($parameterData['type'])
                    );
                    $parameterData['setter'] = $this->getDummyDataGeneratorBetween($parameterData['type'], floatval($arguments[0]), fieldName: $parameterData['name']);
                    break;
                case 'max':
                    $parameterData['description'] .= ' ' . $this->getDescription(
                        $rule,
                        [':max' => $arguments[0]],
                        $this->getLaravelValidationBaseTypeMapping($parameterData['type'])
                    );
                    $max = min($arguments[0], 25);
                    $parameterData['setter'] = $this->getDummyDataGeneratorBetween($parameterData['type'], 1, $max, $parameterData['name']);
                    break;
                case 'between':
                    $parameterData['description'] .= ' ' . $this->getDescription(
                        $rule,
                        [':min' => $arguments[0], ':max' => $arguments[1]],
                        $this->getLaravelValidationBaseTypeMapping($parameterData['type'])
                    );
                    // Avoid exponentially complex operations by using the minimum length
                    $parameterData['setter'] = $this->getDummyDataGeneratorBetween($parameterData['type'], floatval($arguments[0]), floatval($arguments[0]) + 1, $parameterData['name']);
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
                    // Not using the rule description here because it only says "The attribute is invalid"
                    $parameterData['description'] .= ' Must be one of ' . w::getListOfValuesAsFriendlyHtmlString($arguments) . ' ';
                    $parameterData['setter'] = function () use ($arguments) {
                        return Arr::random($arguments);
                    };
                    break;

                    /**
                     * These rules only add a description. Generating valid examples is too complex.
                     */
                case 'not_in':
                    $parameterData['description'] .= ' Must not be one of ' . w::getListOfValuesAsFriendlyHtmlString($arguments) . ' ';
                    break;
                case 'required_if':
                    $parameterData['description'] .= ' ' . $this->getDescription(
                        $rule,
                        [':other' => "<code>{$arguments[0]}</code>", ':value' => w::getListOfValuesAsFriendlyHtmlString(array_slice($arguments, 1))]
                    ) . ' ';
                    break;
                case 'required_unless':
                    $parameterData['description'] .= ' ' . $this->getDescription(
                        $rule,
                        [':other' => "<code>" . array_shift($arguments) . "</code>", ':values' => w::getListOfValuesAsFriendlyHtmlString($arguments)]
                    ) . ' ';
                    break;
                case 'required_with':
                    $parameterData['description'] .= ' ' . $this->getDescription(
                        $rule,
                        [':values' => w::getListOfValuesAsFriendlyHtmlString($arguments)]
                    ) . ' ';
                    break;
                case 'required_without':
                    $description = $this->getDescription(
                        $rule,
                        [':values' => w::getListOfValuesAsFriendlyHtmlString($arguments)]
                    ) . ' ';
                    $parameterData['description'] .= str_replace('must be present', 'is not present', $description);
                    break;
                case 'required_with_all':
                case 'required_without_all':
                    $parameterData['description'] .= ' ' . $this->getDescription(
                        $rule,
                        [':values' => w::getListOfValuesAsFriendlyHtmlString($arguments, "and")]
                    ) . ' ';
                    break;
                case 'accepted_if':
                    $parameterData['type'] = 'boolean';
                    $parameterData['description'] .= " Must be accepted when <code>$arguments[0]</code> is " . w::getListOfValuesAsFriendlyHtmlString(array_slice($arguments, 1));
                    $parameterData['setter'] = fn () => true;
                    break;
                case 'same':
                case 'different':
                    $parameterData['description'] .= ' ' . $this->getDescription(
                        $rule,
                        [':other' => "<code>{$arguments[0]}</code>"]
                    ) . ' ';
                    break;

                default:
                    // Other rules not supported
                    break;
            }

            return true;
        } catch (Throwable $e) {
            throw CouldntProcessValidationRule::forParam($parameterData['name'], $rule, $e);
        }
    }

    /**
     * Parse a string rule into the base rule and arguments.
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

        // Convert any custom Rule objects to strings
        if ($rule instanceof Rule) {
            $className = substr(strrchr(get_class($rule), "\\"), 1);
            return [$className, []];
        }

        if (strpos($rule, ':') !== false) {
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
        } elseif (!is_null($parameterData['example']) && $parameterData['example'] !== self::$MISSING_VALUE) {
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
                // (assuming the user specified parents first, which is the more common thing)
                if ($childKey = Arr::first($allKeys, fn ($key) => Str::startsWith($key, "$name.*"))) {
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
                $hasExample = $this->examplePresent($details);

                // Change cars.*.dogs.things.*.* with type X to cars.*.dogs.things with type X[][]
                while (Str::endsWith($name, '.*')) {
                    $details['type'] .= '[]';
                    $name = substr($name, 0, -2);

                    if ($hasExample) {
                        $details['example'] = [$details['example']];
                    } elseif (isset($details['setter'])) {
                        $previousSetter = $details['setter'];
                        $details['setter'] = fn () => [$previousSetter()];
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

        $description = trans("validation.{$rule}");
        // For rules that can apply to multiple types (eg 'max' rule), Laravel returns an array of possible messages
        // 'numeric' => 'The :attribute must not be greater than :max'
        // 'file' => 'The :attribute must have a size less than :max kilobytes'
        if (is_array($description)) {
            $description = $description[$baseType];
        }

        // Convert messages from failure type ("The :attribute is not a valid date.") to info ("The :attribute must be a valid date.")
        $description = str_replace(['is not', 'does not'], ['must be', 'must'], $description);
        $description = str_replace('may not', 'must not', $description);

        foreach ($arguments as $placeholder => $argument) {
            $description = str_replace($placeholder, $argument, $description);
        }

        // For rules that validate subfields
        $description = str_replace("The :attribute field ", "This field ", $description);

        return str_replace(
            [" :attribute ", "The value must ", " 1 characters", " 1 digits", " 1 kilobytes"],
            [" value ", "Must ", " 1 character", " 1 digit", " 1 kilobyte"],
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
}
