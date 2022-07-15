<?php

namespace Knuckles\Scribe\Extracting\Strategies;

use Knuckles\Scribe\Extracting\ParamHelpers;

class GetParamsFromAttributeStrategy extends PhpAttributeStrategy
{
    use ParamHelpers;

    protected function extractFromAttributes(array $attributesOnMethod, array $attributesOnController): ?array
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
        $data['type'] = $this->normalizeTypeName($data['type']);
        if (is_null($data['example'])) {
            $data['example'] = $this->generateDummyValue($data['type']);
        } else if ($data['example'] == 'No-example' || $data['example'] == 'No-example.') {
            $data['example'] = null;
        }

        $data['description'] = trim($data['description'] ?? '');
        return $data;
    }
}
