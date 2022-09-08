<?php

namespace Knuckles\Scribe\Extracting\Strategies\Responses;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Exception;
use Illuminate\Support\Arr;
use Knuckles\Scribe\Extracting\DatabaseTransactionHelpers;
use Knuckles\Scribe\Extracting\InstantiatesExampleModels;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Knuckles\Scribe\Extracting\Shared\TransformerResponseTools;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\AnnotationParser as a;
use Knuckles\Scribe\Tools\Utils;
use Mpociot\Reflection\DocBlock\Tag;
use ReflectionClass;
use ReflectionFunctionAbstract;

/**
 * Parse a transformer response from the docblock ( @transformer || @transformercollection ).
 */
class UseTransformerTags extends Strategy
{
    use DatabaseTransactionHelpers, InstantiatesExampleModels;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        $methodDocBlock = RouteDocBlocker::getDocBlocksFromRoute($endpointData->route)['method'];
        $tags = $methodDocBlock->getTags();
        return $this->getTransformerResponseFromTags($tags);
    }

    /**
     * Get a response from the @transformer/@transformerCollection and @transformerModel tags.
     *
     * @param Tag[] $allTags
     *
     * @return array|null
     */
    public function getTransformerResponseFromTag(Tag $transformerTag, array $allTags): ?array
    {
        [$statusCode, $transformerClass, $isCollection] = $this->getStatusCodeAndTransformerClass($transformerTag);
        [$model, $factoryStates, $relations, $resourceKey] = $this->getClassToBeTransformed($allTags);

        $modelInstantiator = fn() => $this->instantiateExampleModel($model, $factoryStates, $relations, (new ReflectionClass($transformerClass))->getMethod('transform'));
        $pagination = $this->getTransformerPaginatorData($allTags);
        $serializer = $this->config->get('fractal.serializer');

        $this->startDbTransaction();
        $content = TransformerResponseTools::fetch(
            $transformerClass, $isCollection, $modelInstantiator, $pagination, $resourceKey, $serializer
        );
        $this->endDbTransaction();

        return [
            [
                'status' => $statusCode ?: 200,
                'content' => $content,
            ],
        ];
    }

    private function getStatusCodeAndTransformerClass(Tag $tag): array
    {
        preg_match('/^(\d{3})?\s?([\s\S]*)$/', $tag->getContent(), $result);
        $status = (int)($result[1] ?: 200);
        $transformerClass = $result[2];
        $isCollection = strtolower($tag->getName()) == 'transformercollection';

        return [$status, $transformerClass, $isCollection];
    }

    /**
     * @param array $tags
     *
     * @return array
     * @throws Exception
     *
     */
    private function getClassToBeTransformed(array $tags): array
    {
        $modelTag = Arr::first(Utils::filterDocBlockTags($tags, 'transformermodel'));

        $type = null;
        $states = [];
        $relations = [];
        $resourceKey = null;
        if ($modelTag) {
            ['content' => $type, 'fields' => $fields] = a::parseIntoContentAndFields($modelTag->getContent(), ['states', 'with', 'resourceKey']);
            $states = $fields['states'] ? explode(',', $fields['states']) : [];
            $relations = $fields['with'] ? explode(',', $fields['with']) : [];
            $resourceKey = $fields['resourceKey'] ?? null;
        }

        return [$type, $states, $relations, $resourceKey];
    }

    private function getTransformerTag(array $tags): ?Tag
    {
        return Arr::first(Utils::filterDocBlockTags($tags, 'transformer', 'transformercollection'));
    }

    /**
     * Gets pagination data from the `@transformerPaginator` tag, like this:
     * `@transformerPaginator League\Fractal\Pagination\IlluminatePaginatorAdapter 15`
     *
     * @param Tag[] $tags
     *
     * @return array
     */
    private function getTransformerPaginatorData(array $tags): array
    {
        $tag = Arr::first(Utils::filterDocBlockTags($tags, 'transformerpaginator'));
        if (empty($tag)) {
            return ['adapter' => null, 'perPage' => null];
        }

        preg_match('/^\s*(.+?)(\s+\d+)?$/', $tag->getContent(), $result);
        $paginatorAdapter = $result[1];
        $perPage = $result[2] ?? null;
        if ($perPage) {
            $perPage = trim($perPage);
        }

        return ['adapter' => $paginatorAdapter, 'perPage' => $perPage ?: null];
    }

    public function getTransformerResponseFromTags(array $tags): ?array
    {
        if (empty($transformerTag = $this->getTransformerTag($tags))) {
            return null;
        }

        return $this->getTransformerResponseFromTag($transformerTag, $tags);
    }

}
