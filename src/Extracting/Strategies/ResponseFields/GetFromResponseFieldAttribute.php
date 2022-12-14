<?php

namespace Knuckles\Scribe\Extracting\Strategies\ResponseFields;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Extracting\Shared\ResponseFieldTools;
use Knuckles\Scribe\Extracting\Strategies\PhpAttributeStrategy;
use Knuckles\Scribe\Tools\Utils as u;
use ReflectionAttribute;

/**
 * @extends PhpAttributeStrategy<ResponseField>
 */
class GetFromResponseFieldAttribute extends PhpAttributeStrategy
{
    protected static array $attributeNames = [ResponseField::class];

    protected function extractFromAttributes(
        ExtractedEndpointData $endpointData,
        array $attributesOnMethod, array $attributesOnFormRequest = [], array $attributesOnController = []
    ): ?array
    {
        $attributesOnApiResourceMethods = [];
        $apiResourceAttributes = $endpointData->method->getAttributes(ResponseFromApiResource::class);

        if (!empty($apiResourceAttributes)) {
            $attributesOnApiResourceMethods = collect($apiResourceAttributes)
                ->flatMap(function (ReflectionAttribute $attribute) {
                    $className = $attribute->newInstance()->name;
                    $method = u::getReflectedRouteMethod([$className, 'toArray']);
                    return collect($method->getAttributes(ResponseField::class))
                        ->map(fn (ReflectionAttribute $attr) => $attr->newInstance());
                });
        }


        return collect([...$attributesOnController, ...$attributesOnFormRequest, ...$attributesOnMethod, ...$attributesOnApiResourceMethods])
            ->mapWithKeys(function ($attributeInstance) use ($endpointData) {
                /** @var ResponseField $attributeInstance */
                $data = $attributeInstance->toArray();

                $data['type'] = ResponseFieldTools::inferTypeOfResponseField($data, $endpointData);

                return [$data['name'] => $data];
            })->toArray();
    }
}
