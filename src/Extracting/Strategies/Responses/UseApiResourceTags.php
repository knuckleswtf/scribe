<?php

namespace Knuckles\Scribe\Extracting\Strategies\Responses;

use Illuminate\Support\Str;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Knuckles\Scribe\Extracting\DatabaseTransactionHelpers;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\AnnotationParser as a;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\ErrorHandlingUtils as e;
use Knuckles\Scribe\Tools\Utils;
use Mpociot\Reflection\DocBlock\Tag;
use Throwable;

/**
 * Parse an Eloquent API resource response from the docblock ( @apiResource || @apiResourcecollection ).
 */
class UseApiResourceTags extends Strategy
{
    use DatabaseTransactionHelpers;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules): ?array
    {
        $docBlocks = RouteDocBlocker::getDocBlocksFromRoute($endpointData->route);
        $methodDocBlock = $docBlocks['method'];

        $tags = $methodDocBlock->getTags();
        if (empty($apiResourceTag = $this->getApiResourceTag($tags))) {
            return null;
        }

        $this->startDbTransaction();

        try {
            return $this->getApiResourceResponse($apiResourceTag, $tags, $endpointData);
        } catch (Exception $e) {
            c::warn('Exception thrown when fetching Eloquent API resource response for ' . $endpointData->name());
            e::dumpExceptionIfVerbose($e);

            return null;
        } finally {
            $this->endDbTransaction();
        }
    }

    /**
     * Get a response from the @apiResource/@apiResourceCollection, @apiResourceModel and @apiResourceAdditional tags.
     *
     * @param Tag $apiResourceTag
     * @param Tag[] $allTags
     * @param ExtractedEndpointData $endpointData
     *
     * @return array[]|null
     * @throws Exception
     */
    public function getApiResourceResponse(Tag $apiResourceTag, array $allTags, ExtractedEndpointData $endpointData): ?array
    {
        [$statusCode, $apiResourceClass] = $this->getStatusCodeAndApiResourceClass($apiResourceTag);
        [$model, $factoryStates, $relations, $pagination] = $this->getClassToBeTransformedAndAttributes($allTags);
        $additionalData = $this->getAdditionalData($this->getApiResourceAdditionalTag($allTags));
        $modelInstance = $this->instantiateApiResourceModel($model, $factoryStates, $relations);

        try {
            $resource = new $apiResourceClass($modelInstance);
        } catch (Exception $e) {
            // If it is a ResourceCollection class, it might throw an error
            // when trying to instantiate with something other than a collection
            $resource = new $apiResourceClass(collect([$modelInstance]));
        }
        if (strtolower($apiResourceTag->getName()) == 'apiresourcecollection') {
            // Collections can either use the regular JsonResource class (via `::collection()`,
            // or a ResourceCollection (via `new`)
            // See https://laravel.com/docs/5.8/eloquent-resources
            $models = [$modelInstance, $this->instantiateApiResourceModel($model, $factoryStates, $relations)];
            // Pagination can be in two forms:
            // [15] : means ::paginate(15)
            // [15, 'simple'] : means ::simplePaginate(15)
            if (count($pagination) == 1) {
                $perPage = $pagination[0];
                $paginator = new LengthAwarePaginator(
                // For some reason, the LengthAware paginator needs only first page items to work correctly
                    collect($models)->slice(0, $perPage),
                    count($models),
                    $perPage
                );
                $list = $paginator;
            } elseif (count($pagination) == 2 && $pagination[1] == 'simple') {
                $perPage = $pagination[0];
                $paginator = new Paginator($models, $perPage);
                $list = $paginator;
            } else {
                $list = collect($models);
            }
            /** @var JsonResource $resource */
            $resource = $resource instanceof ResourceCollection
                ? new $apiResourceClass($list)
                : $apiResourceClass::collection($list);
        }

        // Adding additional meta information for our resource response
        $resource = $resource->additional($additionalData);

        $uri = Utils::getUrlWithBoundParameters($endpointData->route->uri(), $endpointData->cleanUrlParameters);
        $method = $endpointData->route->methods()[0];
        $request = Request::create($uri, $method);
        $request->headers->add(['Accept' => 'application/json']);
        app()->bind('request', function () use ($request) {
            return $request;
        });

        $route = $endpointData->route;
        /** @var Response $response */
        $response = $resource->toResponse(
            // Set the route properly so it works for users who have code that checks for the route.
            $request->setRouteResolver(function () use ($route) {
                return $route;
            })
        );

        return [
            [
                'status' => $statusCode ?: 200,
                'content' => $response->getContent(),
            ],
        ];
    }

    /**
     * @param Tag $tag
     *
     * @return array
     */
    private function getStatusCodeAndApiResourceClass($tag): array
    {
        $content = $tag->getContent();
        preg_match('/^(\d{3})?\s?([\s\S]*)$/', $content, $result);
        $status = (int)($result[1] ?: 0);
        $apiResourceClass = $result[2];

        return [$status, $apiResourceClass];
    }

    private function getClassToBeTransformedAndAttributes(array $tags): array
    {
        $modelTag = Arr::first(array_filter($tags, function ($tag) {
            return ($tag instanceof Tag) && strtolower($tag->getName()) == 'apiresourcemodel';
        }));

        $type = null;
        $states = [];
        $relations = [];
        $pagination = [];
        if ($modelTag) {
            ['content' => $type, 'attributes' => $attributes] = a::parseIntoContentAndAttributes($modelTag->getContent(), ['states', 'with', 'paginate']);
            $states = $attributes['states'] ? explode(',', $attributes['states']) : [];
            $relations = $attributes['with'] ? explode(',', $attributes['with']) : [];
            $pagination = $attributes['paginate'] ? explode(',', $attributes['paginate']) : [];
        }

        if (empty($type)) {
            throw new Exception("Couldn't detect an Eloquent API resource model from your docblock. Did you remember to specify a model using @apiResourceModel?");
        }

        return [$type, $states, $relations, $pagination];
    }

    /**
     * Returns data for simulating JsonResource additional() function
     *
     * @param Tag|null $tag
     * @return array
     */
    private function getAdditionalData(?Tag $tag): array
    {
        return $tag
            ? a::parseIntoAttributes($tag->getContent())
            : [];
    }

    /**
     * @param string $type
     *
     * @param array $relations
     * @param array $factoryStates
     *
     * @return Model|object
     */
    protected function instantiateApiResourceModel(string $type, array $factoryStates = [], array $relations = [])
    {
        try {
            // Try Eloquent model factory
            $factory = Utils::getModelFactory($type, $factoryStates, $relations);

            try {
                return $factory->create()->load($relations);
            } catch (Throwable $e) {
                c::warn("Eloquent model factory failed to create {$type}; trying to make it.");
                e::dumpExceptionIfVerbose($e, true);

                // If there was no working database, ->create() would fail. Try ->make() instead
                return $factory->make();
            }
        } catch (Throwable $e) {
            c::warn("Eloquent model factory failed to instantiate {$type}; trying to fetch from database.");
            e::dumpExceptionIfVerbose($e, true);

            $instance = new $type();
            if ($instance instanceof \Illuminate\Database\Eloquent\Model) {
                try {
                    // We can't use a factory but can try to get one from the database
                    $firstInstance = $type::with($relations)->first();
                    if ($firstInstance) {
                        return $firstInstance;
                    }
                } catch (Throwable $e) {
                    // okay, we'll stick with `new`
                    c::warn("Failed to fetch first {$type} from database; using `new` to instantiate.");
                    e::dumpExceptionIfVerbose($e);
                }
            }
        }

        return $instance;
    }

    /**
     * @param Tag[] $tags
     *
     * @return Tag|null
     */
    public function getApiResourceTag(array $tags): ?Tag
    {
        $apiResourceTags = array_values(
            array_filter($tags, function ($tag) {
                return ($tag instanceof Tag) && in_array(strtolower($tag->getName()), ['apiresource', 'apiresourcecollection']);
            })
        );

        return Arr::first($apiResourceTags);
    }

    public function getApiResourceAdditionalTag(array $tags): ?Tag
    {
        return Arr::first($tags, function ($tag) {
            return ($tag instanceof Tag) && strtolower($tag->getName()) == 'apiresourceadditional';
        });
    }
}
