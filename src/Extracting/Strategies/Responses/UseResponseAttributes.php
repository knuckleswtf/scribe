<?php

namespace Knuckles\Scribe\Extracting\Strategies\Responses;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\ResponseFromFile;
use Knuckles\Scribe\Attributes\ResponseFromTransformer;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\Strategies\PhpAttributeStrategy;

/**
 * @extends PhpAttributeStrategy<Response|ResponseFromFile|ResponseFromApiResource|ResponseFromTransformer>
 */
class UseResponseAttributes extends PhpAttributeStrategy
{
    use ParamHelpers;

    protected array $attributeNames = [
        Response::class,
        ResponseFromFile::class,
        ResponseFromApiResource::class,
        ResponseFromTransformer::class,
    ];

    protected function extractFromAttributes(array $attributesOnMethod, array $attributesOnController, ExtractedEndpointData $endpointData): ?array
    {
        $responses = [];
        foreach ($attributesOnController as $attributeInstance) {
            $responses[] = $attributeInstance->toArray();
        }
        foreach ($attributesOnMethod as $attributeInstance) {
            $responses[] = $attributeInstance->toArray();
        }

        return $responses;
    }

}
