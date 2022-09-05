<?php

namespace Knuckles\Scribe\Extracting\Shared;

use Illuminate\Pagination\LengthAwarePaginator;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;

class TransformerResponseTools
{
    public static function fetch(string $transformerClass, bool $isCollection, $modelInstantiator, array $pagination = [], ?string $resourceKey = null, ?string $serializer = null)
    {
        $fractal = new Manager();

        if (!is_null($serializer)) {
            $fractal->setSerializer(app($serializer));
        }

        $modelInstance = $modelInstantiator();
        if ($isCollection) {
            $models = [$modelInstance, $modelInstantiator()];
            $resource = new Collection($models, new $transformerClass());

            ['adapter' => $paginatorAdapter, 'perPage' => $perPage] = $pagination;
            if ($paginatorAdapter) {
                $total = count($models);
                // Need to pass only the first page to both adapter and paginator, otherwise they will display ebverything
                $firstPage = collect($models)->slice(0, $perPage);
                $resource = new Collection($firstPage, new $transformerClass(), $resourceKey);
                $paginator = new LengthAwarePaginator($firstPage, $total, $perPage);
                $resource->setPaginator(new $paginatorAdapter($paginator));
            }
        } else {
            $resource = (new Item($modelInstance, new $transformerClass(), $resourceKey));
        }

        return response($fractal->createData($resource)->toJson())->getContent();
    }
}
