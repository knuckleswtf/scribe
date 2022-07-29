<?php

namespace Knuckles\Scribe\Extracting\Strategies;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;

abstract class Strategy
{
    public ?ExtractedEndpointData $endpointData;

    public function __construct(protected DocumentationConfig $config)
    {
    }

    /**
     * Returns an instance of the documentation config
     *
     * @return DocumentationConfig
     */
    public function getConfig(): DocumentationConfig
    {
        return $this->config;
    }

    /**
     * @param ExtractedEndpointData $endpointData
     * @param array $routeRules Array of rules for the ruleset which this route belongs to.
     *
     * @return array|null
     */
    abstract public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array;
}
