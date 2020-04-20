<?php

namespace Knuckles\Scribe\Extracting\Strategies\ResponseFields;

use Exception;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use ReflectionClass;
use ReflectionFunctionAbstract;

class UseParsedResponses extends Strategy
{
    /**
     * @param Route $route
     * @param ReflectionClass $controller
     * @param ReflectionFunctionAbstract $method
     * @param array $rulesToApply
     * @param array $context
     *
     * @return array|null
     *@throws Exception
     *
     */
    public function __invoke(Route $route, ReflectionClass $controller, ReflectionFunctionAbstract $method, array $rulesToApply, array $context = [])
    {
        $responses = $context['responses'];

        $successfulResponse = json_decode(Arr::first($responses, function ($response) {
           return $response['status'] >= 200 && $response['status'] < 300;
        })['content'] ?? null, true);

        if (! is_array($successfulResponse)) {
            return [];
        }

        // If the first key is 0, we assume it's a numeric array, ergo a list of items
        $responseWithFields = key($successfulResponse) === 0
            ? $successfulResponse[0] : $successfulResponse;

        $fields = [];

        foreach ($responseWithFields as $field => $value) {
            $type = gettype($value);

            if ($type == "double") {
                $type = "float";
            }

            if ($type == "array" && key($successfulResponse) !== 0) {
                $type = "object";
            }

            $fields[] = [
                'name' => $field,
                'value' => $value,
                'type' => $type,
                'description' => "",
            ];
        }

        return $fields;
    }

}
