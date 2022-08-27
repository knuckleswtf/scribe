<?php

namespace Knuckles\Scribe\Extracting\Strategies\Responses;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Exception;
use Illuminate\Support\Arr;
use Knuckles\Scribe\Extracting\DatabaseTransactionHelpers;
use Knuckles\Scribe\Extracting\InstantiatesExampleModels;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Knuckles\Scribe\Extracting\Shared\ApiResourceResponseTools;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\AnnotationParser as a;
use Knuckles\Scribe\Tools\Utils;
use Mpociot\Reflection\DocBlock\Tag;

/**
 * Parse an Eloquent API resource response from the docblock ( @apiResource || @apiResourcecollection ).
 */
class UseApiResourceTags extends Strategy
{
    use DatabaseTransactionHelpers, InstantiatesExampleModels;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        $methodDocBlock = RouteDocBlocker::getDocBlocksFromRoute($endpointData->route)['method'];

        $tags = $methodDocBlock->getTags();
        if (empty($apiResourceTag = $this->getApiResourceTag($tags))) {
            return null;
        }

        return $this->getApiResourceResponseFromTags($apiResourceTag, $tags, $endpointData);
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
    public function getApiResourceResponseFromTags(Tag $apiResourceTag, array $allTags, ExtractedEndpointData $endpointData): ?array
    {
        [$statusCode, $description, $apiResourceClass, $isCollection] = $this->getStatusCodeAndApiResourceClass($apiResourceTag);
        [$modelClass, $factoryStates, $relations, $pagination] = $this->getClassToBeTransformedAndAttributes($allTags);
        $additionalData = $this->getAdditionalData($allTags);

        $modelInstantiator = fn() => $this->instantiateExampleModel($modelClass, $factoryStates, $relations);

        $this->startDbTransaction();
        $content = ApiResourceResponseTools::fetch(
            $apiResourceClass, $isCollection, $modelInstantiator,
            $endpointData, $pagination, $additionalData,
        );
        $this->endDbTransaction();

        return [
            [
                'status' => $statusCode ?: 200,
                'description' => $description,
                'content' => $content,
            ],
        ];
    }

    private function getStatusCodeAndApiResourceClass(Tag $tag): array
    {
        preg_match('/^(\d{3})?\s?([\s\S]*)$/', $tag->getContent(), $result);

        $status = $result[1] ?: 0;
        $content = $result[2];

        ['fields' => $fields, 'content' => $content] = a::parseIntoContentAndFields($content, ['status', 'scenario']);

        $status = $fields['status'] ?: $status;
        $apiResourceClass = $content;
        $description = $fields['scenario'] ? "$status, {$fields['scenario']}" : "$status";

        $isCollection = strtolower($tag->getName()) == 'apiresourcecollection';
        return [(int)$status, $description, $apiResourceClass, $isCollection];
    }

    private function getClassToBeTransformedAndAttributes(array $tags): array
    {
        $modelTag = Arr::first(Utils::filterDocBlockTags($tags, 'apiresourcemodel'));

        $modelClass = null;
        $states = [];
        $relations = [];
        $pagination = [];

        if ($modelTag) {
            ['content' => $modelClass, 'fields' => $fields] = a::parseIntoContentAndFields($modelTag->getContent(), ['states', 'with', 'paginate']);
            $states = $fields['states'] ? explode(',', $fields['states']) : [];
            $relations = $fields['with'] ? explode(',', $fields['with']) : [];
            $pagination = $fields['paginate'] ? explode(',', $fields['paginate']) : [];
        }

        if (empty($modelClass)) {
            throw new Exception("Couldn't detect an Eloquent API resource model from your docblock. Did you remember to specify a model using @apiResourceModel?");
        }

        return [$modelClass, $states, $relations, $pagination];
    }

    /**
     * Returns data for simulating JsonResource ->additional() function
     *
     * @param Tag[] $tags
     *
     * @return array
     */
    private function getAdditionalData(array $tags): array
    {
        $tag = Arr::first(Utils::filterDocBlockTags($tags, 'apiresourceadditional'));
        return $tag ? a::parseIntoFields($tag->getContent()) : [];
    }

    public function getApiResourceTag(array $tags): ?Tag
    {
        return Arr::first(Utils::filterDocBlockTags($tags, 'apiresource', 'apiresourcecollection'));
    }
}
