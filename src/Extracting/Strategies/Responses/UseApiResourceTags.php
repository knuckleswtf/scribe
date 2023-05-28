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
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\Utils;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use ReflectionClass;

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
        [$statusCode, $description, $apiResourceClass, $isCollection, $extra] = $this->getStatusCodeAndApiResourceClass($apiResourceTag);
        [$modelClass, $factoryStates, $relations, $pagination] = $this->getClassToBeTransformedAndAttributes($allTags, $apiResourceClass, $extra);
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

        [
            'fields' => $fields,
            'content' => $content
        ] = a::parseIntoContentAndFields($content, static::apiResourceAllowedFields());


        $status = $fields['status'] ?: $status;
        $apiResourceClass = $content;
        $description = $fields['scenario'] ?: "";

        $isCollection = strtolower($tag->getName()) == 'apiresourcecollection';
        return [
            (int)$status,
            $description,
            $apiResourceClass,
            $isCollection,
            collect($fields)->only(...static::apiResourceExtraFields())->toArray(),
        ];
    }

    protected function getClassToBeTransformedAndAttributes(array $tags, string $apiResourceClass, array $extra): array
    {
        $modelTag = Arr::first(Utils::filterDocBlockTags($tags, 'apiresourcemodel'));

        $modelClass = null;

        if ($modelTag) {
            ['content' => $modelClass, 'fields' => $fields] = a::parseIntoContentAndFields($modelTag->getContent(), static::apiResourceModelAllowedFields());
        }

        $fields = array_merge($extra, $fields ?? []);
        $states = $fields['states'] ? explode(',', $fields['states']) : [];
        $relations = $fields['with'] ? explode(',', $fields['with']) : [];
        $pagination = $fields['paginate'] ? explode(',', $fields['paginate']) : [];

        if (empty($modelClass)) {
            $modelClass = ApiResourceResponseTools::tryToInferApiResourceModel($apiResourceClass);
        }

        if (empty($modelClass)) {
            c::warn(<<<WARN
                Couldn't detect an Eloquent API resource model from your `@apiResource`.
                Either specify a model using the `@apiResourceModel` annotation, or add an `@mixin` annotation in your resource's docblock.
                WARN
            );
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

    // These fields were originally only set on @apiResourceModel, but now we also support them on @apiResource
    public static function apiResourceExtraFields()
    {
        return ['states', 'with', 'paginate'];
    }

    public static function apiResourceAllowedFields()
    {
        return ['status', 'scenario', ...static::apiResourceExtraFields()];
    }

    public static function apiResourceModelAllowedFields()
    {
        return ['states', 'with', 'paginate'];
    }

    public function getApiResourceTag(array $tags): ?Tag
    {
        return Arr::first(Utils::filterDocBlockTags($tags, 'apiresource', 'apiresourcecollection'));
    }
}
