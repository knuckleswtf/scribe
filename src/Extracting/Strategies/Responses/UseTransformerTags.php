<?php

namespace Knuckles\Scribe\Extracting\Strategies\Responses;

use Exception;
use Illuminate\Database\Eloquent\Model as IlluminateModel;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Knuckles\Scribe\Extracting\DatabaseTransactionHelpers;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\AnnotationParser as a;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\ErrorHandlingUtils as e;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use ReflectionClass;
use ReflectionFunctionAbstract;

/**
 * Parse a transformer response from the docblock ( @transformer || @transformercollection ).
 */
class UseTransformerTags extends Strategy
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
     * @throws \Exception
     *
     */
    public function __invoke(Route $route, ReflectionClass $controller, ReflectionFunctionAbstract $method, array $rulesToApply, array $alreadyExtractedData = [])
    {
        $docBlocks = RouteDocBlocker::getDocBlocksFromRoute($route);
        /** @var DocBlock $methodDocBlock */
        $methodDocBlock = $docBlocks['method'];

        try {
            return $this->getTransformerResponse($methodDocBlock->getTags());
        } catch (Exception $e) {
            c::warn('Exception thrown when fetching transformer response for [' . implode(',', $route->methods) . "] {$route->uri}.");
            e::dumpExceptionIfVerbose($e);

            return null;
        }
    }

    /**
     * Get a response from the @transformer/@transformerCollection and @transformerModel tags.
     *
     * @param Tag[] $tags
     *
     * @return array|null
     */
    public function getTransformerResponse(array $tags)
    {
        if (empty($transformerTag = $this->getTransformerTag($tags))) {
            return null;
        }

        [$statusCode, $transformer] = $this->getStatusCodeAndTransformerClass($transformerTag);
        [$model, $factoryStates, $relations] = $this->getClassToBeTransformed($tags, (new ReflectionClass($transformer))->getMethod('transform'));
        $modelInstance = $this->instantiateTransformerModel($model, $factoryStates, $relations);

        $fractal = new Manager();

        if (!is_null($this->config->get('fractal.serializer'))) {
            $fractal->setSerializer(app($this->config->get('fractal.serializer')));
        }

        if ((strtolower($transformerTag->getName()) == 'transformercollection')) {
            $models = [$modelInstance, $this->instantiateTransformerModel($model, $factoryStates, $relations)];
            $resource = new Collection($models, new $transformer());

            ['adapter' => $paginatorAdapter, 'perPage' => $perPage] = $this->getTransformerPaginatorData($tags);
            if ($paginatorAdapter) {
                $total = count($models);
                // Need to pass only the first page to both adapter and paginator, otherwise they will display ebverything
                $firstPage = collect($models)->slice(0, $perPage);
                $resource = new Collection($firstPage, new $transformer());
                $paginator = new LengthAwarePaginator($firstPage, $total, $perPage);
                $resource->setPaginator(new $paginatorAdapter($paginator));
            }
        } else {
            $resource = new Item($modelInstance, new $transformer());
        }

        $response = response($fractal->createData($resource)->toJson());

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
    private function getStatusCodeAndTransformerClass($tag): array
    {
        $content = $tag->getContent();
        preg_match('/^(\d{3})?\s?([\s\S]*)$/', $content, $result);
        $status = $result[1] ?: 200;
        $transformerClass = $result[2];

        return [$status, $transformerClass];
    }

    /**
     * @param array $tags
     * @param ReflectionFunctionAbstract $transformerMethod
     *
     * @return array
     * @throws Exception
     *
     */
    private function getClassToBeTransformed(array $tags, ReflectionFunctionAbstract $transformerMethod): array
    {
        $modelTag = Arr::first(array_filter($tags, function ($tag) {
            return ($tag instanceof Tag) && strtolower($tag->getName()) == 'transformermodel';
        }));

        $type = null;
        $states = [];
        $relations = [];
        if ($modelTag) {
            ['content' => $type, 'attributes' => $attributes] = a::parseIntoContentAndAttributes($modelTag->getContent(), ['states', 'with']);
            $states = $attributes['states'] ? explode(',', $attributes['states']) : [];
            $relations = $attributes['with'] ? explode(',', $attributes['with']) : [];
        } else {
            $parameter = Arr::first($transformerMethod->getParameters());
            if ($parameter->hasType() && !$parameter->getType()->isBuiltin() && class_exists($parameter->getType()->getName())) {
                // Ladies and gentlemen, we have a type!
                $type = $parameter->getType()->getName();
            }
        }

        if ($type == null) {
            throw new Exception("Couldn't detect a transformer model from your doc block. Did you remember to specify a model using @transformerModel?");
        }

        return [$type, $states, $relations];
    }

    protected function instantiateTransformerModel(string $type, array $factoryStates = [], array $relations = [])
    {
        $this->startDbTransaction();
        try {
            // try Eloquent model factory

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
            if ($instance instanceof IlluminateModel) {
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
    private function getTransformerTag(array $tags)
    {
        $transformerTags = array_values(
            array_filter($tags, function ($tag) {
                return ($tag instanceof Tag) && in_array(strtolower($tag->getName()), ['transformer', 'transformercollection']);
            })
        );

        return Arr::first($transformerTags);
    }

    /**
     * Gets pagination data from the `@transformerPaginator` tag, like this:
     * `@transformerPaginator League\Fractal\Pagination\IlluminatePaginatorAdapter 15`
     *
     * @param array $tags
     *
     * @return array
     */
    private function getTransformerPaginatorData(array $tags)
    {
        $transformerTags = array_values(
            array_filter($tags, function ($tag) {
                return ($tag instanceof Tag) && in_array(strtolower($tag->getName()), ['transformerpaginator']);
            })
        );

        $tag = Arr::first($transformerTags);
        if (empty($tag)) {
            return ['adapter' => null, 'perPage' => null];
        }

        $content = $tag->getContent();
        preg_match('/^\s*(.+?)\s+(\d+)?$/', $content, $result);
        $paginatorAdapter = $result[1];
        $perPage = $result[2] ?? null;

        return ['adapter' => $paginatorAdapter, 'perPage' => $perPage];
    }
}
