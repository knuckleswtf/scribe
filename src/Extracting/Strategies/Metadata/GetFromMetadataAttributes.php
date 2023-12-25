<?php

namespace Knuckles\Scribe\Extracting\Strategies\Metadata;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Subgroup;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\Strategies\PhpAttributeStrategy;

/**
 * @extends PhpAttributeStrategy<Group|Subgroup|Endpoint|Authenticated>
 */
class GetFromMetadataAttributes extends PhpAttributeStrategy
{
    use ParamHelpers;

    protected static array $attributeNames = [
        Group::class,
        Subgroup::class,
        Endpoint::class,
        Authenticated::class,
        Unauthenticated::class,
    ];

    protected function extractFromAttributes(
        ExtractedEndpointData $endpointData,
        array $attributesOnMethod,
        array $attributesOnFormRequest = [],
        array $attributesOnController = []
    ): ?array
    {
        $metadata = [
            "groupName" => "",
            "groupDescription" => "",
            "subgroup" => "",
            "subgroupDescription" => "",
            "title" => "",
            "description" => "",
        ];
        foreach ([...$attributesOnController, ...$attributesOnFormRequest, ...$attributesOnMethod] as $attributeInstance) {
            $metadata = array_merge($metadata, $attributeInstance->toArray());
        }

        return $metadata;
    }

}
