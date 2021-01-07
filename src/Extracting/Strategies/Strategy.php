<?php

namespace Knuckles\Scribe\Extracting\Strategies;

use Knuckles\Camel\Extraction\EndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;

abstract class Strategy
{
    /**
     * The Scribe config
     */
    protected DocumentationConfig $config;

    /**
     * The current stage of route processing
     */
    public string $stage;

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
     * @param EndpointData $endpointData
     * @param array $routeRules Array of rules for the ruleset which this route belongs to.
     *
     * @return array|null
     */
    abstract public function __invoke(EndpointData $endpointData, array $routeRules);
}
