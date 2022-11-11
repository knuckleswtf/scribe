<?php

namespace Knuckles\Scribe\Extracting\Strategies\Responses;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\ResponseFromFile;
use Knuckles\Scribe\Attributes\ResponseFromTransformer;
use Knuckles\Scribe\Extracting\DatabaseTransactionHelpers;
use Knuckles\Scribe\Extracting\InstantiatesExampleModels;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\Shared\ApiResourceResponseTools;
use Knuckles\Scribe\Extracting\Shared\TransformerResponseTools;
use Knuckles\Scribe\Extracting\Strategies\PhpAttributeStrategy;
use ReflectionClass;

/**
 * @extends PhpAttributeStrategy<Response|ResponseFromFile|ResponseFromApiResource|ResponseFromTransformer>
 */
class UseResponseAttributes extends PhpAttributeStrategy
{
    use ParamHelpers, DatabaseTransactionHelpers, InstantiatesExampleModels;

    protected static array $attributeNames = [
        Response::class,
        ResponseFromFile::class,
        ResponseFromApiResource::class,
        ResponseFromTransformer::class,
    ];

    protected function extractFromAttributes(
        array $attributesOnMethod, array $attributesOnController,
        ExtractedEndpointData $endpointData
    ): ?array
    {
        $responses = [];
        foreach ([...$attributesOnController, ...$attributesOnMethod] as $attributeInstance) {
            /* @phpstan-ignore-next-line */
            $responses[] = match (get_class($attributeInstance)) {
                Response::class => $attributeInstance->toArray(),
                ResponseFromFile::class => $attributeInstance->toArray(),
                ResponseFromApiResource::class => $this->getApiResourceResponse($attributeInstance),
                ResponseFromTransformer::class => $this->getTransformerResponse($attributeInstance),
            };
        }

        return $responses;
    }

    protected function getApiResourceResponse(ResponseFromApiResource $attributeInstance)
    {
        $modelInstantiator = fn() => $this->instantiateExampleModel($attributeInstance->model, $attributeInstance->factoryStates, $attributeInstance->with);

        $pagination = [];
        if ($attributeInstance->paginate) {
            $pagination = [$attributeInstance->paginate];
        } else if ($attributeInstance->simplePaginate) {
            $pagination = [$attributeInstance->simplePaginate, 'simple'];
        }


        $this->startDbTransaction();
        $content = ApiResourceResponseTools::fetch(
            $attributeInstance->name, $attributeInstance->collection, $modelInstantiator,
            $this->endpointData, $pagination, $attributeInstance->additional,
        );
        $this->endDbTransaction();

        return [
            'status' => $attributeInstance->status,
            'description' => $attributeInstance->description,
            'content' => $content,
        ];
    }

    protected function getTransformerResponse(ResponseFromTransformer $attributeInstance)
    {
        $modelInstantiator = fn() => $this->instantiateExampleModel(
            $attributeInstance->model, $attributeInstance->factoryStates, $attributeInstance->with,
            (new ReflectionClass($attributeInstance->name))->getMethod('transform')
        );

        $pagination = $attributeInstance->paginate ? [
            'perPage' => $attributeInstance->paginate[1] ?? null, 'adapter' => $attributeInstance->paginate[0]
        ] : [];
        $this->startDbTransaction();
        $content = TransformerResponseTools::fetch(
            $attributeInstance->name, $attributeInstance->collection, $modelInstantiator,
            $pagination, $attributeInstance->resourceKey, $this->config->get('fractal.serializer'),
        );
        $this->endDbTransaction();

        return [
            'status' => $attributeInstance->status,
            'description' => $attributeInstance->description,
            'content' => $content,
        ];
    }

}
