<?php

namespace Knuckles\Scribe\Extracting\Strategies\ResponseFields;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Camel\Extraction\Response;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Extracting\Strategies\PhpAttributeStrategy;
use stdClass;

/**
 * @extends PhpAttributeStrategy<ResponseField>
 */
class GetFromResponseFieldAttribute extends PhpAttributeStrategy
{
    protected string $attributeName = ResponseField::class;

    protected function extractFromAttributes(array $attributesOnMethod, array $attributesOnController, ExtractedEndpointData $endpointData): ?array
    {
        return collect([...$attributesOnController, ...$attributesOnMethod])
            ->mapWithKeys(function ($attributeInstance) use ($endpointData) {
                $data = $attributeInstance->toArray();

                $data['type'] = $this->inferTypeOfResponseField($data, $endpointData);

                return [$data['name'] => $data];
            })->toArray();
    }

    protected function inferTypeOfResponseField(array $data, ExtractedEndpointData $endpointData): string
    {
        if (empty($data['type'])) {
            // Try to get a type from first 2xx response
            $validResponse = collect($endpointData->responses)->first(
                fn(Response $r) => $r->status >= 200 && $r->status < 300
            );
            if ($validResponse && ($validResponseContent = json_decode($validResponse->content, true))) {
                $nonexistent = new stdClass();
                $value = $validResponseContent[$data['name']]
                    ?? $validResponseContent['data'][$data['name']] // Maybe it's a Laravel ApiResource
                    ?? $validResponseContent[0][$data['name']] // Maybe it's a list
                    ?? $validResponseContent['data'][0][$data['name']] // Maybe an Api Resource Collection?
                    ?? $nonexistent;

                if ($value !== $nonexistent) {
                    return $this->normalizeTypeName(gettype($value), $value);
                }
            }
        }

        return $this->normalizeTypeName($data['type'] ?? "string");
    }
}
