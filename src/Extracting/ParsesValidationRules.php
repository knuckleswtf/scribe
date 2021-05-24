<?php

namespace Knuckles\Scribe\Extracting;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\WritingUtils as w;

trait ParsesValidationRules
{
    use ParamHelpers;

    public static \stdClass $MISSING_VALUE;

    public function getBodyParametersFromValidationRules(array $validationRules, array $customParameterData = []): array
    {
        self::$MISSING_VALUE = new \stdClass();
        $validationRules = $this->normaliseRules($validationRules);

        $parameters = [];
        foreach ($validationRules as $parameter => $ruleset) {
            if (count($customParameterData) && !isset($customParameterData[$parameter])) {
                c::debug($this->getMissingCustomDataMessage($parameter));
            }
            $userSpecifiedParameterInfo = $customParameterData[$parameter] ?? [];

            $parameterData = [
                'name' => $parameter,
                'required' => false,
                'type' => null,
                'example' => self::$MISSING_VALUE,
                'description' => '',
            ];

            // Make sure the user-specified example overwrites others.
            if (isset($userSpecifiedParameterInfo['example'])) {
                $parameterData['example'] = $userSpecifiedParameterInfo['example'];
            }

            foreach ($ruleset as $rule) {
                $this->parseRule($rule, $parameterData);
            }
            if ($this->shouldCastUserExample() && isset($userSpecifiedParameterInfo['example'])) {
                // Examples in comments are strings, we need to cast them properly
                $parameterData['example'] = $this->castToType($userSpecifiedParameterInfo['example'], $parameterData['type'] ?? 'string');
            }

            $parameterData = $this->setParameterExampleAndDescription($parameterData, $userSpecifiedParameterInfo);

            $parameterData['name'] = $parameter;
            $parameters[$parameter] = $parameterData;
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
            if (!Str::contains($key, '.*')) continue;

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
     * @param $rule
     * @param $parameterData
     */
    protected function parseRule($rule, &$parameterData)
    {
        // Convert string rules into rule + arguments (eg "in:1,2" becomes ["in", ["1", "2"]])
        $parsedRule = $this->parseStringRuleIntoRuleAndArguments($rule);
        [$rule, $arguments] = $parsedRule;

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
                $parameterData['setter'] = function () {
                    return $this->generateDummyValue('string');
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
                $parameterData['description'] .= 'The value must be a file.';
                $parameterData['setter'] = function () {
                    return $this->generateDummyValue('file');
                };
                break;

            /**
             * Special string types
             */
            case 'timezone':
                // Laravel's message merely says "The value must be a valid zone"
                $parameterData['description'] .= "The value must be a valid time zone, such as <code>Africa/Accra</code>. ";
                $parameterData['setter'] = function () {
                    return $this->getFaker()->timezone;
                };
                break;
            case 'email':
                $parameterData['description'] .= $this->getDescription($rule) . ' ';
                $parameterData['setter'] = function () {
                    return $this->getFaker()->safeEmail;
                };
                $parameterData['type'] = 'string';
                break;
            case 'url':
                $parameterData['setter'] = function () {
                    return $this->getFaker()->url;
                };
                $parameterData['type'] = 'string';
                // Laravel's message is "The value format is invalid". Ugh.🤮
                $parameterData['description'] .= "The value must be a valid URL. ";
                break;
            case 'ip':
                $parameterData['description'] .= $this->getDescription($rule) . ' ';
                $parameterData['type'] = 'string';
                $parameterData['setter'] = function () {
                    return $this->getFaker()->ipv4;
                };
                break;
            case 'json':
                $parameterData['type'] = 'string';
                $parameterData['description'] .= $this->getDescription($rule) . ' ';
                $parameterData['setter'] = function () {
                    return json_encode([$this->getFaker()->word, $this->getFaker()->word,]);
                };
                break;
            case 'date':
                $parameterData['type'] = 'string';
                $parameterData['description'] .= $this->getDescription($rule) . ' ';
                $parameterData['setter'] = function () {
                    return date('Y-m-d\TH:i:s', time());
                };
                break;
            case 'date_format':
                $parameterData['type'] = 'string';
                // Laravel description here is "The value must match the format Y-m-d". Not descriptive enough.
                $parameterData['description'] .= "The value must be a valid date in the format {$arguments[0]} ";
                $parameterData['setter'] = function () use ($arguments) {
                    return date($arguments[0], time());
                };
                break;

            /**
             * Special number types. Some rules here may apply to other types, but we treat them as being numeric.
             */
            /*
             * min, max and between not supported until we can figure out a proper way
             *  to make them compatible with multiple types (string, number, file)
            case 'min':
                $parameterData['type'] = $parameterData['type'] ?: 'number';
                $parameterData['description'] .= Description::getDescription($rule, [':min' => $arguments[0]], 'numeric').' ';
                $parameterData['setter'] = function () { return $this->getFaker()->numberBetween($arguments[0]); };
                break;
            case 'max':
                $parameterData['type'] = $parameterData['type'] ?: 'number';
                $parameterData['description'] .= Description::getDescription($rule, [':max' => $arguments[0]], 'numeric').' ';
                $parameterData['setter'] = function () { return $this->getFaker()->numberBetween(0, $arguments[0]); };
                break;
            case 'between':
                $parameterData['type'] = $parameterData['type'] ?: 'number';
                $parameterData['description'] .= Description::getDescription($rule, [':min' => $arguments[0], ':max' => $arguments[1]], 'numeric').' ';
                $parameterData['setter'] = function () { return $this->getFaker()->numberBetween($arguments[0], $arguments[1]); };
                break;*/

            /**
             * Special file types.
             */
            case 'image':
                $parameterData['type'] = 'file';
                $parameterData['description'] .= $this->getDescription($rule) . ' ';
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
                $description = 'The value must be one of ' . w::getListOfValuesAsFriendlyHtmlString($arguments);
                $parameterData['description'] .= $description . ' ';
                $parameterData['setter'] = function () use ($arguments) {
                    return Arr::random($arguments);
                };
                break;

            default:
                // Other rules not supported
                break;
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

        // Convert any Rule objects to strings
        if ($rule instanceof Rule) {
            $className = substr(strrchr(get_class($rule), "\\"), 1);
            return [$className, []];
        }

        if (strpos($rule, ':') !== false) {
            [$rule, $argumentsString] = explode(':', $rule, 2);

            // These rules can have ommas in their arguments, so we don't split on commas
            if (in_array(strtolower($rule), ['regex', 'date', 'date_format'])) {
                $ruleArguments = [$argumentsString];
            } else {
                $ruleArguments = str_getcsv($argumentsString);
            }
        }

        return [strtolower(trim($rule)), $ruleArguments];
    }

    protected function setParameterExampleAndDescription(array $parameterData, array $userSpecifiedParameterInfo): array
    {
        // If no example was given by the user, set an autogenerated example.
        // Each parsed rule returns a 'setter' function. We'll evaluate the last one.
        if ($parameterData['example'] === self::$MISSING_VALUE && isset($parameterData['setter'])) {
            $parameterData['example'] = $parameterData['setter']();
        }

        // Make sure the user-specified description comes first (and add full stops where needed).
        $userSpecifiedDescription = $userSpecifiedParameterInfo['description'] ?? '';
        if (!empty($userSpecifiedDescription) && !Str::endsWith($userSpecifiedDescription, '.')) {
            $userSpecifiedDescription .= '.';
        }
        $validationDescription = trim($parameterData['description'] ?: '');
        $fullDescription = trim($userSpecifiedDescription . ' ' . trim($validationDescription));
        $parameterData['description'] = $fullDescription ? rtrim($fullDescription, '.') . '.' : $fullDescription;

        // Set a default type
        if (is_null($parameterData['type'])) {
            $parameterData['type'] = 'string';
        }
        // If the parameter is required and has no example, generate one.
        if ($parameterData['required'] === true && $parameterData['example'] === self::$MISSING_VALUE) {
            $parameterData['example'] = $this->generateDummyValue($parameterData['type']);
        }

        if (!is_null($parameterData['example']) && $parameterData['example'] !== self::$MISSING_VALUE) {
            // Casting again is important since values may have been cast to string in the validator
            $parameterData['example'] = $this->castToType($parameterData['example'], $parameterData['type']);
        }
        return $parameterData;
    }

    /**
     * Laravel uses .* notation for arrays. This PR aims to normalise that into our "new syntax".
     *
     * 'years.*' with type 'integer' becomes 'years' with type 'integer[]'
     * 'cars.*.age' with type 'string' becomes 'cars[].age' with type 'string' and 'cars' with type 'object[]'
     * 'cars.*.things.*.*' with type 'string' becomes 'cars[].things' with type 'string[][]' and 'cars' with type
     * 'object[]'
     *
     * @param array[] $bodyParametersFromValidationRules
     *
     * @return array
     */
    public function normaliseArrayAndObjectParameters(array $bodyParametersFromValidationRules): array
    {
        $results = [];
        foreach ($bodyParametersFromValidationRules as $name => $details) {
            if (isset($results[$name])) {
                continue;
            }
            if ($details['type'] === 'array') {
                // Generic array type. If a child item exists,
                // this will be overwritten with the correct type (such as object or object[]) by the code below
                $details['type'] = 'string[]';
            }

            if (Str::endsWith($name, '.*')) {
                // Wrap array example properly
                $needsWrapping = !is_array($details['example']);

                $nestingLevel = 0;
                // Change cars.*.dogs.things.*.* with type X to cars.*.dogs.things with type X[][]
                while (Str::endsWith($name, '.*')) {
                    $details['type'] .= '[]';
                    if ($needsWrapping) {
                        // Make it two items in each array
                        $secondItem = $secondValue = $details['setter']();
                        for ($i = 0; $i < $nestingLevel; $i++) {
                            $secondItem = [$secondValue];
                        }
                        $details['example'] = [$details['example'], $secondItem];
                    }
                    $name = substr($name, 0, -2);
                    $nestingLevel++;
                }
            }

            // Now make sure the field cars.*.dogs exists
            $parentPath = $name;
            while (Str::contains($parentPath, '.')) {
                $parentPath = preg_replace('/\.[^.]+$/', '', $parentPath);
                if (empty($bodyParametersFromValidationRules[$parentPath])) {
                    if (Str::endsWith($parentPath, '.*')) {
                        $parentPath = substr($parentPath, 0, -2);
                        $type = 'object[]';
                        $example = [[]];
                    } else {
                        $type = 'object';
                        $example = [];
                    }
                    $normalisedPath = str_replace('.*.', '[].', $parentPath);
                    $results[$normalisedPath] = [
                        'name' => $normalisedPath,
                        'type' => $type,
                        'required' => false,
                        'description' => '',
                        'example' => $example,
                    ];
                } else {
                    // if the parent field already exists with a type 'array'
                    $parentDetails = $bodyParametersFromValidationRules[$parentPath];
                    unset($bodyParametersFromValidationRules[$parentPath]);
                    if (Str::endsWith($parentPath, '.*')) {
                        $parentPath = substr($parentPath, 0, -2);
                        $parentDetails['type'] = 'object[]';
                        // Set the example too. Very likely the example array was an array of strings or an empty array
                        if (empty($parentDetails['example']) || is_string($parentDetails['example'][0]) || is_string($parentDetails['example'][0][0])) {
                            $parentDetails['example'] = [[]];
                        }
                    } else {
                        $parentDetails['type'] = 'object';
                        if (empty($parentDetails['example']) || is_string($parentDetails['example'][0])) {
                            $parentDetails['example'] = [];
                        }
                    }
                    $normalisedPath = str_replace('.*.', '[].', $parentPath);
                    $parentDetails['name'] = $normalisedPath;
                    $results[$normalisedPath] = $parentDetails;
                }
            }

            $details['name'] = $name = str_replace('.*.', '[].', $name);
            unset($details['setter']);

            // Change type 'array' to 'object' if there are subfields
            if (
                $details['type'] === 'array'
                && Arr::first(array_keys($bodyParametersFromValidationRules), function ($key) use ($name) {
                    return preg_match("/{$name}\\.[^*]/", $key);
                })
            ) {
                $details['type'] = 'object';
            }
            $results[$name] = $details;

        }

        return $results;
    }

    protected function getDescription(string $rule, array $arguments = [], $baseType = 'string'): string
    {
        $description = trans("validation.{$rule}");
        // For rules that can apply to multiple types (eg 'max' rule), Laravel returns an array of possible messages
        // 'numeric' => 'The :attribute must not be greater than :max'
        // 'file' => 'The :attribute must have a size less than :max kilobytes'
        if (is_array($description)) {
            $description = $description[$baseType];
        }

        // Convert messages from failure type ("The value is not a valid date.") to info ("The value must be a valid date.")
        $description = str_replace(['is not', 'does not'], ['must be', 'must'], $description);

        foreach ($arguments as $placeholder => $argument) {
            $description = str_replace($placeholder, $argument, $description);
        }

        return str_replace(":attribute", "value", $description);
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
