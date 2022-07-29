<?php

namespace Knuckles\Scribe\Extracting\Shared;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\ErrorHandlingUtils as e;
use Knuckles\Scribe\Tools\Utils;

class ApiResourceResponseTools
{
    public static function fetch(
        string $apiResourceClass, bool $isCollection, $modelInstantiator,
        ExtractedEndpointData $endpointData, array $pagination, array $additionalData
    )
    {
        try {
            $resource = ApiResourceResponseTools::getApiResourceOrCollectionInstance(
                $apiResourceClass, $isCollection, $modelInstantiator, $pagination, $additionalData
            );
            $response = ApiResourceResponseTools::getApiResourceResponse($resource, $endpointData);
            return $response->getContent();
        } catch (Exception $e) {
            c::warn('Exception thrown when fetching Eloquent API resource response for ' . $endpointData->name());
            e::dumpExceptionIfVerbose($e);

            return null;
        }
    }

    public static function getApiResourceResponse(JsonResource $resource, ExtractedEndpointData $endpointData): JsonResponse
    {
        $uri = Utils::getUrlWithBoundParameters($endpointData->route->uri(), $endpointData->cleanUrlParameters);
        $method = $endpointData->route->methods()[0];
        $request = Request::create($uri, $method);
        $request->headers->add(['Accept' => 'application/json']);
        app()->bind('request', fn() => $request);

        // Set the route properly, so it works for users who have code that checks for the route.
        return $resource->toResponse(
            $request->setRouteResolver(fn() => $endpointData->route)
        );
    }

    public static function getApiResourceOrCollectionInstance(
        string $apiResourceClass, bool $isCollection, $modelInstantiator,
        array  $paginationStrategy = [], array $additionalData = []
    ): JsonResource
    {
        $modelInstance = $modelInstantiator();
        try {
            $resource = new $apiResourceClass($modelInstance);
        } catch (Exception) {
            // If it is a ResourceCollection class, it might throw an error
            // when trying to instantiate with something other than a collection
            $resource = new $apiResourceClass(collect([$modelInstance]));
        }

        if ($isCollection) {
            // Collections can either use the regular JsonResource class (via `::collection()`,
            // or a ResourceCollection (via `new`)
            // See https://laravel.com/docs/5.8/eloquent-resources
            $models = [$modelInstance, $modelInstantiator()];
            // Pagination can be in two forms:
            // [15] : means ::paginate(15)
            // [15, 'simple'] : means ::simplePaginate(15)
            if (count($paginationStrategy) == 1) {
                $perPage = $paginationStrategy[0];
                $paginator = new LengthAwarePaginator(
                // For some reason, the LengthAware paginator needs only first page items to work correctly
                    collect($models)->slice(0, $perPage), count($models), $perPage
                );
                $list = $paginator;
            } elseif (count($paginationStrategy) == 2 && $paginationStrategy[1] == 'simple') {
                $perPage = $paginationStrategy[0];
                $paginator = new Paginator($models, $perPage);
                $list = $paginator;
            } else {
                $list = collect($models);
            }
            /** @var JsonResource $resource */
            $resource = $resource instanceof ResourceCollection
                ? new $apiResourceClass($list) : $apiResourceClass::collection($list);
        }

        return $resource->additional($additionalData);
    }
}
