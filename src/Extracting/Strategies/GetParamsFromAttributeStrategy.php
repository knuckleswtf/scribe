<?php

namespace Knuckles\Scribe\Extracting\Strategies;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Attributes\GenericParam;

/**
 * @template T of GenericParam
 * @extends PhpAttributeStrategy<T>
 */
class GetParamsFromAttributeStrategy extends PhpAttributeStrategy
{
    use ParamHelpers;

    protected function extractFromAttributes(
        array $attributesOnMethod, array $attributesOnController,
        ExtractedEndpointData $endpointData): ?array
    {
        $parameters = [];
        foreach ($attributesOnController as $attributeInstance) {
            $parameters[$attributeInstance->name] = $attributeInstance->toArray();
        }
        foreach ($attributesOnMethod as $attributeInstance) {
            $parameters[$attributeInstance->name] = $attributeInstance->toArray();
        }

        return array_map([$this, 'normalizeParameterData'], $parameters);
    }

    protected function normalizeParameterData(array $data): array
    {
        $data['type'] = static::normalizeTypeName($data['type']);
        if (is_null($data['example'])) {
            $data['example'] = $this->generateDummyValue($data['type']);
        } else if ($data['example'] == 'No-example' || $data['example'] == 'No-example.') {
            $data['example'] = null;
        }

        $data['description'] = trim($data['description'] ?? '');
        return $data;
    }
}
