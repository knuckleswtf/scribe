<?php

namespace Knuckles\Scribe\Extracting\Shared;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\ErrorHandlingUtils as e;
use Knuckles\Scribe\Tools\Utils;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;

class ApiResourceResponseTools
{
    public static function fetch(
        string $apiResourceClass,
        bool $isCollection,
        ?callable $modelInstantiator,
        ExtractedEndpointData $endpointData,
        array $pagination,
        array $additionalData,
        array $with = [],
        bool $documentJsonApiQueryParameters = false,
    ) {
        $response = static::fetchResponse(
            $apiResourceClass,
            $isCollection,
            $modelInstantiator,
            $endpointData,
            $pagination,
            $additionalData,
            $with,
            $documentJsonApiQueryParameters,
        );

        return $response?->getContent();
    }

    /**
     * Same as `fetch()`, but hands you the whole response, so you can read its headers.
     * Returns null if a JSON:API resource couldn't be rendered (a warning is printed in that case).
     *
     * @param  string[]  $with  The relations the example model was loaded with. Only used for JSON:API resources.
     */
    public static function fetchResponse(
        string $apiResourceClass,
        bool $isCollection,
        ?callable $modelInstantiator,
        ExtractedEndpointData $endpointData,
        array $pagination,
        array $additionalData,
        array $with = [],
        bool $documentJsonApiQueryParameters = false,
    ): ?JsonResponse {
        $instantiate = fn () => static::getApiResourceOrCollectionInstance(
            $apiResourceClass,
            $isCollection,
            $modelInstantiator,
            $pagination,
            $additionalData
        );

        if (! JsonApiResourceTools::isJsonApiResourceClass($apiResourceClass)) {
            return static::callApiResourceAndGetResponse($instantiate(), $endpointData);
        }

        // JSON:API only renders the relationships listed in the `include` query parameter.
        $queryParameters = JsonApiResourceTools::queryParametersForRequest($with);

        // A JSON:API resource needs an id and a type, so it blows up if we couldn't find a model for it.
        // It does so with a TypeError, which isn't an Exception, so it would escape the `catch` in
        // GroupedEndpointsFromApp and take down the whole generation, not just this endpoint.
        try {
            $resource = $instantiate();
            $response = static::callApiResourceAndGetResponse($resource, $endpointData, $queryParameters);
        } catch (\Throwable $e) {
            c::warn(
                "Couldn't render your JSON:API resource {$apiResourceClass}. "
                .'JSON:API responses need an id and a type, so make sure Scribe can find an example model for the resource '
                .'(via `@apiResourceModel`, the `model:` parameter, or an `@mixin` annotation in the resource\'s docblock).'
            );
            e::dumpExceptionIfVerbose($e);

            return null;
        }

        JsonApiResourceTools::documentRequestHeaders($resource, $endpointData);

        if ($documentJsonApiQueryParameters) {
            JsonApiResourceTools::documentQueryParameters(
                $resource, $endpointData, static::createRequest($endpointData, $queryParameters), $with
            );
        }

        return $response;
    }

    /**
     * @param  array<string, string>  $queryParameters
     */
    public static function callApiResourceAndGetResponse(
        JsonResource $resource,
        ExtractedEndpointData $endpointData,
        array $queryParameters = [],
    ): JsonResponse {
        $request = static::createRequest($endpointData, $queryParameters);
        $request->headers->add([
            'Accept' => JsonApiResourceTools::isJsonApiResource($resource)
                ? JsonApiResourceTools::CONTENT_TYPE
                : 'application/json',
        ]);

        $previousBoundRequest = app('request');
        app()->bind('request', fn () => $request);

        try {
            return $resource->toResponse($request);
        } finally {
            // Rendering a resource can throw (a JSON:API one with no model does), and leaving our
            // synthetic request bound would then hand it to every endpoint extracted after this one.
            app()->bind('request', fn () => $previousBoundRequest);
        }
    }

    /**
     * Build the synthetic request we render the resource against.
     *
     * @param  array<string, string>  $queryParameters
     */
    public static function createRequest(ExtractedEndpointData $endpointData, array $queryParameters = []): Request
    {
        $uri = Utils::getUrlWithBoundParameters($endpointData->route->uri(), $endpointData->cleanUrlParameters);
        if ($queryParameters) {
            // Passing these to `Request::create()` as parameters would put them in the body for non-GET routes.
            $uri .= (str_contains($uri, '?') ? '&' : '?').http_build_query($queryParameters);
        }

        $request = Request::create($uri, $endpointData->route->methods()[0]);
        // Set the route properly, so it works for users who have code that checks for the route.
        $request->setRouteResolver(fn () => $endpointData->route);

        return $request;
    }

    public static function getApiResourceOrCollectionInstance(
        string $apiResourceClass,
        bool $isCollection,
        ?callable $modelInstantiator,
        array $paginationStrategy = [],
        array $additionalData = [],
    ): JsonResource {
        // If the API Resource uses an empty $resource (e.g. an empty array), the $modelInstantiator will be null
        // See https://github.com/knuckleswtf/scribe/issues/652
        $modelInstance = is_callable($modelInstantiator) ? $modelInstantiator() : [];

        try {
            $resource = new $apiResourceClass($modelInstance);
        } catch (\Exception) {
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
            if (count($paginationStrategy) === 1) {
                $perPage = $paginationStrategy[0];
                $paginator = new LengthAwarePaginator(
                    // For some reason, the LengthAware paginator needs only first page items to work correctly
                    collect($models)->slice(0, $perPage),
                    count($models),
                    $perPage
                );
                $list = $paginator;
            } elseif (count($paginationStrategy) === 2 && $paginationStrategy[1] === 'simple') {
                $perPage = $paginationStrategy[0];
                $paginator = new Paginator($models, $perPage);
                $list = $paginator;
            } elseif (count($paginationStrategy) === 2 && $paginationStrategy[1] === 'cursor') {
                $perPage = $paginationStrategy[0];
                $paginator = new CursorPaginator($models, $perPage);
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

    /**
     * Check if the ApiResource class has an `@mixin` docblock, and fetch the model from there.
     */
    public static function tryToInferApiResourceModel(string $apiResourceClass): ?string
    {
        $class = new \ReflectionClass($apiResourceClass);
        $docBlock = new DocBlock($class->getDocComment() ?: '');

        /** @var null|Tag $mixinTag */
        $mixinTag = Arr::first(Utils::filterDocBlockTags($docBlock->getTags(), 'mixin'));
        if (empty($mixinTag) || empty($modelClass = mb_trim($mixinTag->getContent()))) {
            return null;
        }

        if (class_exists($modelClass)) {
            return $modelClass;
        }

        return null;
    }
}
