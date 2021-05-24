<?php

namespace Knuckles\Scribe\Extracting\Strategies\BodyParameters;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Dingo\Api\Http\FormRequest as DingoFormRequest;
use Illuminate\Foundation\Http\FormRequest as LaravelFormRequest;
use Knuckles\Scribe\Extracting\ParsesValidationRules;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use ReflectionClass;
use ReflectionException;
use ReflectionFunctionAbstract;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use ReflectionUnionType;

class GetFromFormRequest extends Strategy
{
    use ParsesValidationRules;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules): array
    {
        return $this->getBodyParametersFromFormRequest($endpointData->method, $endpointData->route);
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
                /** @var LaravelFormRequest|DingoFormRequest $formRequest */
                $formRequest = new $parameterClassName;
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

    protected function getMissingCustomDataMessage($parameterName)
    {
        return "No data found for parameter '$parameterName' in your bodyParameters() method. Add an entry for '$parameterName' so you can add a description and example.";
    }

}

