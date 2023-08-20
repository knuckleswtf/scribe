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
        ExtractedEndpointData $endpointData,
        array $attributesOnMethod, array $attributesOnFormRequest = [], array $attributesOnController = []
    ): ?array
    {
        $parameters = [];
        foreach ([...$attributesOnController, ...$attributesOnFormRequest, ...$attributesOnMethod] as $attributeInstance) {
            $parameters[$attributeInstance->name] = $attributeInstance->toArray();
        }
        return array_map([$this, 'normalizeParameterData'], $parameters);
    }

    protected function normalizeParameterData(array $data): array
    {
        $data['type'] = static::normalizeTypeName($data['type']);
        if (is_null($data['example'])) {
            $data['example'] = $this->generateDummyValue($data['type'], [
                'name' => $data['name'],
                'enumValues' => $data['enumValues'],
            ]);
        } else if ($data['example'] == 'No-example' || $data['example'] == 'No-example.') {
            $data['example'] = null;
        }

        $data['description'] = trim($data['description'] ?? '');
        return $data;
    }
}
