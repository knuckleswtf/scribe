<?php

namespace Knuckles\Scribe\Extracting\Strategies\ResponseFields;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Extracting\Shared\ResponseFieldTools;
use Knuckles\Scribe\Extracting\Strategies\PhpAttributeStrategy;
use Knuckles\Scribe\Tools\Utils as u;

/**
 * @extends PhpAttributeStrategy<ResponseField>
 */
class GetFromResponseFieldAttribute extends PhpAttributeStrategy
{
    protected static array $attributeNames = [ResponseField::class];

    protected function extractFromAttributes(
        ExtractedEndpointData $endpointData,
        array $attributesOnMethod,
        array $attributesOnFormRequest = [],
        array $attributesOnController = [],
    ): ?array {
        return [
            ...$this->getNonApiResourceFields($endpointData, $attributesOnMethod, $attributesOnFormRequest, $attributesOnController),
            ...$this->getApiResourceFields($endpointData),
        ];
    }

    protected function getApiResourceFields(ExtractedEndpointData $endpointData): array
    {
        $apiResourceAttributes = $endpointData->method->getAttributes(ResponseFromApiResource::class);

        return collect($apiResourceAttributes)
            ->flatMap(fn (\ReflectionAttribute $attribute) => $this->extractFieldsFromApiResource($attribute, $endpointData))
            ->toArray();
    }

    protected function extractFieldsFromApiResource(\ReflectionAttribute $attribute, ExtractedEndpointData $endpointData): array
    {
        $className = $attribute->newInstance()->name;
        $method = u::getReflectedRouteMethod([$className, 'toArray']);
        $wrapKey = $className::$wrap ?? null;

        return collect($method->getAttributes(ResponseField::class))
            ->mapWithKeys(function (\ReflectionAttribute $attr) use ($endpointData, $wrapKey) {
                $data = $attr->newInstance()->toArray();
                $data['type'] = ResponseFieldTools::inferTypeOfResponseField($data, $endpointData);

                if ($wrapKey !== null) {
                    $data['name'] = $wrapKey.'.'.$data['name'];
                }

                return [$data['name'] => $data];
            })->toArray();
    }

    protected function getNonApiResourceFields(
        ExtractedEndpointData $endpointData,
        array $attributesOnMethod,
        array $attributesOnFormRequest,
        array $attributesOnController,
    ): array {
        return collect([...$attributesOnController, ...$attributesOnFormRequest, ...$attributesOnMethod])
            ->mapWithKeys(function ($attributeInstance) use ($endpointData) {
                /** @var ResponseField $attributeInstance */
                $data = $attributeInstance->toArray();

                $data['type'] = ResponseFieldTools::inferTypeOfResponseField($data, $endpointData);

                return [$data['name'] => $data];
            })->toArray();
    }
}
