<?php

namespace Knuckles\Scribe\Extracting\Strategies;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\FindsFormRequestForMethod;
use Knuckles\Scribe\Extracting\ParsesValidationRules;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\Globals;

class GetFromFormRequestBase extends Strategy
{
    use FindsFormRequestForMethod;
    use ParsesValidationRules;

    protected string $customParameterDataMethodName = '';

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        return $this->getParametersFromFormRequest($endpointData->method, $endpointData->route);
    }

    public function getParametersFromFormRequest(\ReflectionFunctionAbstract $method, Route $route): array
    {
        if (! $formRequestReflectionClass = $this->getFormRequestReflectionClass($method)) {
            return [];
        }

        if (! $this->isFormRequestMeantForThisStrategy($formRequestReflectionClass)) {
            return [];
        }

        $className = $formRequestReflectionClass->getName();

        if (Globals::$__instantiateFormRequestUsing) {
            $formRequest = call_user_func_array(Globals::$__instantiateFormRequestUsing, [$className, $route, $method]);
        } else {
            $formRequest = new $className;
        }
        // Set the route properly so it works for users who have code that checks for the route.
        /** @var FormRequest $formRequest */
        $formRequest->setRouteResolver(function () use ($formRequest, $route) {
            // Also need to bind the request to the route in case their code tries to inspect current request
            return $route->bind($formRequest);
        });
        $formRequest->server->set('REQUEST_METHOD', $route->methods()[0]);

        $parametersFromFormRequest = $this->getParametersFromValidationRules(
            $this->getRouteValidationRules($formRequest),
            $this->getCustomParameterData($formRequest)
        );

        return $this->normaliseArrayAndObjectParameters($parametersFromFormRequest);
    }

    /**
     * @return mixed
     */
    protected function getRouteValidationRules(FormRequest $formRequest)
    {
        if (method_exists($formRequest, 'validator')) {
            $validationFactory = app(ValidationFactory::class);

            // @phpstan-ignore-next-line
            return app()->call([$formRequest, 'validator'], [$validationFactory])
                ->getRules();
        }
        if (method_exists($formRequest, 'rules')) {
            return app()->call([$formRequest, 'rules']);
        }

        return [];
    }

    protected function getCustomParameterData(FormRequest $formRequest)
    {
        if (method_exists($formRequest, $this->customParameterDataMethodName)) {
            return call_user_func_array([$formRequest, $this->customParameterDataMethodName], []);
        }

        c::warn("No {$this->customParameterDataMethodName}() method found in ".get_class($formRequest).'. Scribe will only be able to extract basic information from the rules() method.');

        return [];
    }

    protected function getMissingCustomDataMessage($parameterName)
    {
        return "No data found for parameter '{$parameterName}' in your {$this->customParameterDataMethodName}() method. Add an entry for '{$parameterName}' so you can add a description and example.";
    }

    protected function isFormRequestMeantForThisStrategy(\ReflectionClass $formRequestReflectionClass): bool
    {
        return $formRequestReflectionClass->hasMethod($this->customParameterDataMethodName);
    }
}
