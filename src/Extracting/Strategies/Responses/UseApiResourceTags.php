<?php

namespace Knuckles\Scribe\Extracting\Strategies\Responses;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Knuckles\Scribe\Extracting\DatabaseTransactionHelpers;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\AnnotationParser as a;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\ErrorHandlingUtils as e;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use ReflectionClass;
use ReflectionFunctionAbstract;

/**
 * Parse an Eloquent API resource response from the docblock ( @apiResource || @apiResourcecollection ).
 */
class UseApiResourceTags extends Strategy
{
    use DatabaseTransactionHelpers;

    /**
     * @param Route $route
     * @param ReflectionClass $controller
     * @param ReflectionFunctionAbstract $method
     * @param array $rulesToApply
     * @param array $alreadyExtractedData
     *
     * @return array|null
     * @throws Exception
     *
     */
    public function __invoke(Route $route, ReflectionClass $controller, ReflectionFunctionAbstract $method, array $rulesToApply, array $alreadyExtractedData = [])
    {
        $docBlocks = RouteDocBlocker::getDocBlocksFromRoute($route);
        /** @var DocBlock $methodDocBlock */
        $methodDocBlock = $docBlocks['method'];

        try {
            return $this->getApiResourceResponse($methodDocBlock->getTags());
        } catch (Exception $e) {
            c::warn('Exception thrown when fetching Eloquent API resource response for [' . implode(',', $route->methods) . "] {$route->uri}.");
            e::dumpExceptionIfVerbose($e);
            return null;
        }
    }

    /**
     * Get a response from the @apiResource/@apiResourceCollection and @apiResourceModel tags.
     *
     * @param Tag[] $tags
     *
     * @return array|null
     */
    public function getApiResourceResponse(array $tags)
    {
        if (empty($apiResourceTag = $this->getApiResourceTag($tags))) {
            return null;
        }

        [$statusCode, $apiResourceClass] = $this->getStatusCodeAndApiResourceClass($apiResourceTag);
        [$model, $factoryStates, $relations, $pagination] = $this->getClassToBeTransformedAndAttributes($tags);
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

        /** @var Response $response */
        $response = $resource->toResponse(app(Request::class));

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
        $status = $result[1] ?: 0;
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
     * @param string $type
     *
     * @param array $relations
     * @param array $factoryStates
     *
     * @return Model|object
     */
    protected function instantiateApiResourceModel(string $type, array $factoryStates = [], array $relations = [])
    {
        $this->startDbTransaction();
        try {
            // Try Eloquent model factory

            // Factories are usually defined without the leading \ in the class name,
            // but the user might write it that way in a comment. Let's be safe.
            $type = ltrim($type, '\\');

            $factory = factory($type);
            if (count($factoryStates)) {
                $factory->states($factoryStates);
            }
            try {
                return $factory->create();
            } catch (Exception $e) {
                // If there was no working database, it would fail.
                return $factory->make();
            }
        } catch (Exception $e) {
            c::debug("Eloquent model factory failed to instantiate {$type}; trying to fetch from database.");
            e::dumpExceptionIfVerbose($e);

            $instance = new $type();
            if ($instance instanceof \Illuminate\Database\Eloquent\Model) {
                try {
                    // we can't use a factory but can try to get one from the database
                    $firstInstance = $type::with($relations)->first();
                    if ($firstInstance) {
                        return $firstInstance;
                    }
                } catch (Exception $e) {
                    // okay, we'll stick with `new`
                    c::debug("Failed to fetch first {$type} from database; using `new` to instantiate.");
                    e::dumpExceptionIfVerbose($e);
                }
            }
        } finally {
            $this->endDbTransaction();
        }

        return $instance;
    }

    /**
     * @param array $tags
     *
     * @return Tag|null
     */
    private function getApiResourceTag(array $tags)
    {
        $apiResourceTags = array_values(
            array_filter($tags, function ($tag) {
                return ($tag instanceof Tag) && in_array(strtolower($tag->getName()), ['apiresource', 'apiresourcecollection']);
            })
        );

        return Arr::first($apiResourceTags);
    }
}
