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
    public $stage;

    public function __construct(DocumentationConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Returns an instance of the documentation config
     *
     * @return DocumentationConfig
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param Route $route The route which we are currently extracting information for.
     * @param ReflectionClass $controller The class handling the current route.
     * @param ReflectionFunctionAbstract $method The method/closure handling the current route.
     * @param array $routeRules Array of rules for the ruleset which this route belongs to.
     * @param array $alreadyExtractedData Data already extracted from previous stages and earlier strategies in this stage
     *
     * @return array|null
     */
    abstract public function __invoke(Route $route, ReflectionClass $controller, ReflectionFunctionAbstract $method, array $routeRules, array $alreadyExtractedData = []);
}
