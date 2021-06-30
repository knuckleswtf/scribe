<?php

namespace Knuckles\Scribe\Extracting\Strategies\BodyParameters;

use ArgumentCountError;
use Dingo\Api\Http\FormRequest as DingoFormRequest;
use Illuminate\Foundation\Http\FormRequest as LaravelFormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Contracts\Validation\Rule;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Extracting\ValidationRuleDescriptionParser as d;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\WritingUtils as w;
use ReflectionClass;
use ReflectionException;
use ReflectionFunctionAbstract;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use ReflectionUnionType;

class GetFromFormRequest extends Strategy
{
    public $stage = 'bodyParameters';

    public static $MISSING_VALUE;

    use ParamHelpers;

    public function __invoke(Route $route, ReflectionClass $controller, ReflectionFunctionAbstract $method, array $routeRules, array $alreadyExtractedData = []): array
    {
        return $this->getBodyParametersFromFormRequest($method, $route);
    }

    public function getBodyParametersFromFormRequest(ReflectionFunctionAbstract $method, $route = null): array
    {
        foreach ($method->getParameters() as $param) {
            $paramType = $param->getType();
            if ($paramType === null) {
                continue;
            }

            if (class_exists(ReflectionUnionType::class)
                && $paramType instanceof ReflectionUnionType) {
                continue;
            }

            $parameterClassName = $paramType->getName();

            if (!class_exists($parameterClassName)) {
                continue;
            }

            try {
                $parameterClass = new ReflectionClass($parameterClassName);
            } catch (ReflectionException $e) {

                dump("Exception: " . $e->getMessage());
                continue;
            }

            // If there's a FormRequest, we check there for @bodyParam tags.
            if (
                (class_exists(LaravelFormRequest::class) && $parameterClass->isSubclassOf(LaravelFormRequest::class))
                || (class_exists(DingoFormRequest::class) && $parameterClass->isSubclassOf(DingoFormRequest::class))) {
                try {
                    /** @var LaravelFormRequest|DingoFormRequest $formRequest */
                    $formRequest = new $parameterClassName;
                } catch (ArgumentCountError $e) {
                    c::info('Skipping instantiation of ' . $parameterClassName . ' because of dependency injection. Use manual @bodyParam to describe this request.');
                    continue;
                }
                
                // Set the route properly so it works for users who have code that checks for the route.
                $formRequest->setRouteResolver(function () use ($formRequest, $route) {
                    // Also need to bind the request to the route in case their code tries to inspect current request
                    return $route->bind($formRequest);
                });
                $bodyParametersFromFormRequest = $this->getBodyParametersFromValidationRules(
                    $this->getRouteValidationRules($formRequest),
                    $this->getCustomParameterData($formRequest)
                );

                return $this->normaliseArrayAndObjectParameters($bodyParametersFromFormRequest);
            }
        }

        return [];
    }

    /**
     * @param LaravelFormRequest|DingoFormRequest $formRequest
     *
     * @return mixed
     */
    protected function getRouteValidationRules($formRequest)
    {
        if (method_exists($formRequest, 'validator')) {
            $validationFactory = app(ValidationFactory::class);

            return call_user_func_array([$formRequest, 'validator'], [$validationFactory])
                ->getRules();
        } else {
            return call_user_func_array([$formRequest, 'rules'], []);
        }
    }

    /**
     * @param LaravelFormRequest|DingoFormRequest $formRequest
     */
    protected function getCustomParameterData($formRequest)
    {
        if (method_exists($formRequest, 'bodyParameters')) {
            return call_user_func_array([$formRequest, 'bodyParameters'], []);
        }

        c::warn("No bodyParameters() method found in " . get_class($formRequest) . " Scribe will only be able to extract basic information from the rules() method.");

        return [];
    }

    public function getBodyParametersFromValidationRules(array $validationRules, array $customParameterData = [])
    {
        self::$MISSING_VALUE = new \stdClass();
        $rules = $this->normaliseRules($validationRules);

        $parameters = [];
        foreach ($rules as $parameter => $ruleset) {
            if (count($customParameterData) && !isset($customParameterData[$parameter])) {
                c::debug("No data found for parameter '$parameter' from your bodyParameters() method. Add an entry for '$parameter' so you can add description and example.");
            }
            $userSpecifiedParameterInfo = $customParameterData[$parameter] ?? [];

            $parameterData = [
                'name' => $parameter,
                'required' => false,
                'type' => null,
                'value' => self::$MISSING_VALUE,
                'description' => '',
            ];

            // Make sure the user-specified example overwrites others.
            if (isset($userSpecifiedParameterInfo['example'])) {
                $parameterData['value'] = $userSpecifiedParameterInfo['example'];
            }

            foreach ($ruleset as $rule) {
                $this->parseRule($rule, $parameterData);
            }

            // Set autogenerated examples if none was supplied.
            // Each rule returns a 'setter' function, so we can lazily evaluate the last one only if we need it.
            if ($parameterData['value'] === self::$MISSING_VALUE && isset($parameterData['setter'])) {
                $parameterData['value'] = $parameterData['setter']();
            }

            // Make sure the user-specified description comes first.
            $userSpecifiedDescription = $userSpecifiedParameterInfo['description'] ?? '';
            $validationDescription = trim($parameterData['description'] ?: '');
            $fullDescription = trim($userSpecifiedDescription . ' ' . trim($validationDescription));
            // Let's have our sentences end with full stops, like civilized people.🙂
            $parameterData['description'] = $fullDescription ? rtrim($fullDescription, '.') . '.' : $fullDescription;

            // Set default values for type
            if (is_null($parameterData['type'])) {
                $parameterData['type'] = 'string';
            }
            // Set values when parameter is required and has no value
            if ($parameterData['required'] === true && $parameterData['value'] === self::$MISSING_VALUE) {
                $parameterData['value'] = $this->generateDummyValue($parameterData['type']);
            }

            if (!is_null($parameterData['value']) && $parameterData['value'] !== self::$MISSING_VALUE) {
                // The cast is important since values may have been cast to string in the validator
                $parameterData['value'] = $this->castToType($parameterData['value'], $parameterData['type']);
            }

            $parameterData['name'] = $parameter;
            $parameters[$parameter] = $parameterData;
        }

        return $parameters;
    }

    /**
     * This method will transform validation rules from:
     * 'param1' => 'int|required'  TO  'param1' => ['int', 'required']
     *
     * @param array<string,string|string[]> $rules
     *
     * @return mixed
     */
    protected function normaliseRules(array $rules)
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

    protected function parseRule($rule, &$parameterData)
    {
        if (!(is_string($rule) || $rule instanceof Rule)) {
            return;
        }

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
                $parameterData['description'] .= d::getDescription($rule) . ' ';
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
                $parameterData['description'] .= d::getDescription($rule) . ' ';
                $parameterData['type'] = 'string';
                $parameterData['setter'] = function () {
                    return $this->getFaker()->ipv4;
                };
                break;
            case 'json':
                $parameterData['type'] = 'string';
                $parameterData['description'] .= d::getDescription($rule) . ' ';
                $parameterData['setter'] = function () {
                    return json_encode([$this->getFaker()->word, $this->getFaker()->word,]);
                };
                break;
            case 'date':
                $parameterData['type'] = 'string';
                $parameterData['description'] .= d::getDescription($rule) . ' ';
                $parameterData['setter'] = function () {
                    return date(\DateTime::ISO8601, time());
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
             *//*
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
                $parameterData['description'] .= d::getDescription($rule) . ' ';
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
    protected function parseStringRuleIntoRuleAndArguments($rule)
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
                $needsWrapping = !is_array($details['value']);

                $nestingLevel = 0;
                // Change cars.*.dogs.things.*.* with type X to cars.*.dogs.things with type X[][]
                while (Str::endsWith($name, '.*')) {
                    $details['type'] .= '[]';
                    if ($needsWrapping) {
                        // Make it two items in each array
                        $secondItem = $secondValue = isset($details['setter'])
                            ? $details['setter']()
                            : null;
                        for ($i = 0; $i < $nestingLevel; $i++) {
                            $secondItem = [$secondValue];
                        }
                        $details['value'] = [$details['value'], $secondItem];
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
                        $value = [[]];
                    } else {
                        $type = 'object';
                        $value = [];
                    }
                    $normalisedPath = str_replace('.*.', '[].', $parentPath);
                    $results[$normalisedPath] = [
                        'name' => $normalisedPath,
                        'type' => $type,
                        'required' => false,
                        'description' => '',
                        'value' => $value,
                    ];
                } else {
                    // if the parent field already exists with a type 'array'
                    $parentDetails = $bodyParametersFromValidationRules[$parentPath];
                    unset($bodyParametersFromValidationRules[$parentPath]);
                    if (Str::endsWith($parentPath, '.*')) {
                        $parentPath = substr($parentPath, 0, -2);
                        $parentDetails['type'] = 'object[]';
                        // Set the example too. Very likely the example array was an array of strings or an empty array
                        if (empty($parentDetails['value']) || is_string($parentDetails['value'][0]) || is_string($parentDetails['value'][0][0])) {
                            $parentDetails['value'] = [[]];
                        }
                    } else {
                        $parentDetails['type'] = 'object';
                        if (empty($parentDetails['value']) || is_string($parentDetails['value'][0])) {
                            $parentDetails['value'] = [];
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
}
