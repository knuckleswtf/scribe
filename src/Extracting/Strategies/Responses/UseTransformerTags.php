<?php

namespace Knuckles\Scribe\Extracting\Strategies\Responses;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as IlluminateModel;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Knuckles\Scribe\Tools\AnnotationParser;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\Flags;
use Knuckles\Scribe\Tools\Utils;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use ReflectionClass;
use ReflectionFunctionAbstract;

/**
 * Parse a transformer response from the docblock ( @transformer || @transformercollection ).
 */
class UseTransformerTags extends Strategy
{
    /**
     * @param Route $route
     * @param ReflectionClass $controller
     * @param ReflectionFunctionAbstract $method
     * @param array $rulesToApply
     * @param array $context
     *
     * @throws \Exception
     *
     * @return array|null
     */
    public function __invoke(Route $route, ReflectionClass $controller, ReflectionFunctionAbstract $method, array $rulesToApply, array $context = [])
    {
        $docBlocks = RouteDocBlocker::getDocBlocksFromRoute($route);
        /** @var DocBlock $methodDocBlock */
        $methodDocBlock = $docBlocks['method'];

        try {
            return $this->getTransformerResponse($methodDocBlock->getTags());
        } catch (Exception $e) {
            clara('knuckleswtf/scribe')->warn('Exception thrown when fetching transformer response for [' . implode(',', $route->methods) . "] {$route->uri}.");
            if (Flags::$shouldBeVerbose) {
                Utils::dumpException($e);
            } else {
                clara('knuckleswtf/scribe')->warn("Run this again with the --verbose flag to see the exception.");
            }

            return null;
        }
    }

    /**
     * Get a response from the @transformer/@transformerCollection and @transformerModel tags.
     *
     * @param array $tags
     * @param Route $route
     *
     * @return array|null
     */
    public function getTransformerResponse(array $tags)
    {
            if (empty($transformerTag = $this->getTransformerTag($tags))) {
                return null;
            }

            [$statusCode, $transformer] = $this->getStatusCodeAndTransformerClass($transformerTag);
            [$model, $factoryStates] = $this->getClassToBeTransformed($tags, (new ReflectionClass($transformer))->getMethod('transform'));
            $modelInstance = $this->instantiateTransformerModel($model, $factoryStates);

            $fractal = new Manager();

            if (! is_null($this->config->get('fractal.serializer'))) {
                $fractal->setSerializer(app($this->config->get('fractal.serializer')));
            }

            $resource = (strtolower($transformerTag->getName()) == 'transformercollection')
                ? new Collection(
                    [$modelInstance, $this->instantiateTransformerModel($model, $factoryStates)],
                    new $transformer()
                )
                : new Item($modelInstance, new $transformer());

            $response = response($fractal->createData($resource)->toJson());

            return [
                [
                    'status' => $statusCode ?: $response->getStatusCode(),
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
     * @throws Exception
     *
     * @return array
     */
    private function getClassToBeTransformed(array $tags, ReflectionFunctionAbstract $transformerMethod): array
    {
        $modelTag = Arr::first(array_filter($tags, function ($tag) {
            return ($tag instanceof Tag) && strtolower($tag->getName()) == 'transformermodel';
        }));

        $type = null;
        $states = [];
        if ($modelTag) {
            ['content' => $type, 'attributes' => $attributes] = AnnotationParser::parseIntoContentAndAttributes($modelTag->getContent(), ['states']);
            $states = explode(',', $attributes['states'] ?? '');
        } else {
            $parameter = Arr::first($transformerMethod->getParameters());
            if ($parameter->hasType() && ! $parameter->getType()->isBuiltin() && class_exists($parameter->getType()->getName())) {
                // Ladies and gentlemen, we have a type!
                $type = $parameter->getType()->getName();
            }
        }

        if ($type == null) {
            throw new Exception("Couldn't detect a transformer model from your docblock. Did you remember to specify a model using @transformerModel?");
        }

        return [$type, $states];
    }

    protected function instantiateTransformerModel(string $type, array $factoryStates = [])
    {
        try {
            // try Eloquent model factory

            // Factories are usually defined without the leading \ in the class name,
            // but the user might write it that way in a comment. Let's be safe.
            $type = ltrim($type, '\\');

            $factory = factory($type);
            if (count($factoryStates)) {
                $factory->states($factoryStates);
            }
            return $factory->make();
        } catch (Exception $e) {
            if (Flags::$shouldBeVerbose) {
                clara('knuckleswtf/scribe')->warn("Eloquent model factory failed to instantiate {$type}; trying to fetch from database.");
            }

            $instance = new $type();
            if ($instance instanceof IlluminateModel) {
                try {
                    // we can't use a factory but can try to get one from the database
                    $firstInstance = $type::first();
                    if ($firstInstance) {
                        return $firstInstance;
                    }
                } catch (Exception $e) {
                    // okay, we'll stick with `new`
                    if (Flags::$shouldBeVerbose) {
                        clara('knuckleswtf/scribe')->warn("Failed to fetch first {$type} from database; using `new` to instantiate.");
                    }
                }
            }
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
}
