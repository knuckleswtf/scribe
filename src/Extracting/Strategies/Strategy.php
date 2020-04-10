<?php

namespace Knuckles\Scribe\Extracting\Strategies;

use Illuminate\Routing\Route;
use Knuckles\Scribe\Tools\DocumentationConfig;
use ReflectionClass;
use ReflectionFunctionAbstract;

abstract class Strategy
{
    /**
     * @var DocumentationConfig The scribe config
     */
    protected $config;

    /**
     * @var string The current stage of route processing
     */
    protected $stage;

    public function __construct(string $stage, DocumentationConfig $config)
    {
        $this->stage = $stage;
        $this->config = $config;
    }

    /**
     * @param Route $route
     * @param ReflectionClass $controller
     * @param ReflectionFunctionAbstract $method
     * @param array $routeRules Array of rules for the ruleset which this route belongs to.
     * @param array $context Results from the previous stages
     *
     * @throws \Exception
     *
     * @return array
     */
    abstract public function __invoke(Route $route, ReflectionClass $controller, ReflectionFunctionAbstract $method, array $routeRules, array $context = []);
}
