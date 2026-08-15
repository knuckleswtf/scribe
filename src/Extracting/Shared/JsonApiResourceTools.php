<?php

namespace Knuckles\Scribe\Extracting\Shared;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection as JsonApiResourceCollection;
use Illuminate\Http\Resources\JsonApi\JsonApiRequest;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Camel\Extraction\Parameter;
use Knuckles\Scribe\Extracting\Extractor;
use Knuckles\Scribe\Tools\DocumentationConfig;

/**
 * Helpers for Laravel's JSON:API resources (`Illuminate\Http\Resources\JsonApi`), added in 12.45.
 */
class JsonApiResourceTools
{
    public const CONTENT_TYPE = 'application/vnd.api+json';

    public static function isSupported(): bool
    {
        return class_exists(JsonApiResource::class);
    }

    public static function shouldDocumentQueryParameters(DocumentationConfig $config): bool
    {
        return (bool) $config->get('json_api.document_query_parameters', true);
    }

    public static function isJsonApiResourceClass(string $apiResourceClass): bool
    {
        return static::isSupported() && is_a($apiResourceClass, JsonApiResource::class, true);
    }

    public static function isJsonApiResource(JsonResource $resource): bool
    {
        return static::isSupported()
            && ($resource instanceof JsonApiResource || $resource instanceof JsonApiResourceCollection);
    }

    /**
     * A JSON:API resource only renders the relationships asked for in the `include` query parameter,
     * so we ask for the same relations the example model was loaded with (`with=` / `with:`).
     *
     * @param  string[]  $with
     * @return array<string, string>
     */
    public static function queryParametersForRequest(array $with): array
    {
        $with = array_values(array_filter(array_map('mb_trim', $with)));

        return $with ? ['include' => implode(',', $with)] : [];
    }

    /**
     * The response headers worth documenting for a JSON:API response, ie its content type.
     *
     * @return array<string, string>
     */
    public static function responseHeaders(string $apiResourceClass, JsonResponse $response): array
    {
        if (! static::isJsonApiResourceClass($apiResourceClass)) {
            return [];
        }

        return array_filter(['content-type' => $response->headers->get('content-type')]);
    }

    /**
     * JSON:API clients are expected to send `Accept: application/vnd.api+json`, so that's what we document.
     *
     * A value the user picked themselves wins, with one exception: the generic `application/json`,
     * which is what the package's own config ships with (the StaticData headers strategy), so on a
     * JSON:API endpoint it's a leftover default rather than a choice.
     */
    public static function documentRequestHeaders(JsonResource $resource, ExtractedEndpointData $endpointData): void
    {
        if (! static::isJsonApiResource($resource)) {
            return;
        }

        $current = $endpointData->headers['Accept'] ?? null;

        if ($current === null || mb_strtolower(mb_trim($current)) === 'application/json') {
            $endpointData->headers['Accept'] = static::CONTENT_TYPE;
        }
    }

    /**
     * Document the `include` and `fields[<type>]` query parameters a JSON:API resource responds to.
     *
     * @param  string[]  $with  The relations the example model was loaded with; used as the example value.
     */
    public static function documentQueryParameters(
        JsonResource $resource,
        ExtractedEndpointData $endpointData,
        Request $request,
        array $with = [],
    ): void {
        if (! static::isJsonApiResource($resource)) {
            return;
        }

        $added = false;

        if ($relationships = static::declaredRelationships($resource, $request)) {
            $added = static::addParameter($endpointData, 'include', new Parameter([
                'name' => 'include',
                'description' => sprintf(
                    'Comma-separated list of relationships to include in the response. Available relationships: %s. '
                    .'Nested relationships are supported, using dot notation (for instance, `author.company`).',
                    static::asCodeList($relationships)
                ),
                // Not `enumValues`: that would render as "Must be one of", and turn into an OpenAPI `enum`,
                // which would declare the perfectly valid `include=a,b` invalid.
                'example' => $with ? implode(',', $with) : null,
                'exampleWasSpecified' => (bool) $with,
            ]));
        }

        foreach (static::sparseFieldsets($resource, $request, $with) as $type => $attributes) {
            $added = static::addParameter($endpointData, "fields[{$type}]", new Parameter([
                'name' => "fields[{$type}]",
                'description' => sprintf(
                    'Comma-separated list of the attributes to return for `%s` resources. Available attributes: %s.',
                    $type, static::asCodeList($attributes)
                ),
                // No example on purpose: unlike `include`, a sparse fieldset doesn't change the example
                // response (we document the full attribute list, ie what you get without it), so putting
                // it in the example request would show a parameter that makes no difference.
                'example' => null,
                'exampleWasSpecified' => false,
            ])) || $added;
        }

        if ($added) {
            // So that response calls (which run after this strategy) actually send the parameters.
            $endpointData->cleanQueryParameters = Extractor::cleanParams($endpointData->queryParameters);
        }
    }

    /**
     * The resource types we can document a sparse fieldset for, mapped to their attribute names:
     * the resource itself, plus the resources of the relationships the example model was loaded with.
     *
     * Related resources are limited to those in `$with` because attribute names have to come from
     * `toAttributes()`, which reads off the model (`['name' => $this->name]`), so we need a loaded
     * related model to call it on.
     *
     * @param  string[]  $with
     * @return array<string, string[]>
     */
    protected static function sparseFieldsets(JsonResource $resource, Request $request, array $with = []): array
    {
        if (! ($element = static::underlyingResource($resource))) {
            return [];
        }

        $fieldsets = [];

        if (($type = static::resourceType($resource, $request)) && ($attributes = static::attributeNames($resource, $request))) {
            $fieldsets[$type] = $attributes;
        }

        if (! $element->resource instanceof Model) {
            return $fieldsets;
        }

        foreach ($with as $relation) {
            // A nested relation (`with=author.company`) ends up in the response's `included` at
            // every level, so we walk it segment by segment, documenting each type on the way.
            $parentResource = $element;
            $parentModel = $element->resource;

            foreach (explode('.', mb_trim($relation)) as $name) {
                $related = static::relatedResource($parentResource, $parentModel, $name, $request);
                if (! $related) {
                    // Nothing more to walk: the relation wasn't loaded, or its resource class
                    // isn't statically known (an int key or a Closure in `toRelationships()`).
                    break;
                }

                [$relatedResource, $relatedModel, $type, $attributes] = $related;

                if ($type && $attributes && ! isset($fieldsets[$type])) {
                    $fieldsets[$type] = $attributes;
                }

                $parentResource = $relatedResource;
                $parentModel = $relatedModel;
            }
        }

        return $fieldsets;
    }

    /**
     * Resolve one segment of a relation path into the resource handling it, the model behind it,
     * and the type and attribute names to document for it.
     *
     * @return array{JsonApiResource, Model, ?string, string[]}|null
     */
    protected static function relatedResource(
        JsonApiResource $parentResource,
        Model $parentModel,
        string $name,
        Request $request,
    ): ?array {
        $relatedResourceClass = static::declaredRelationshipResources($parentResource, $request)[$name] ?? null;

        if (! $relatedResourceClass || ! $parentModel->relationLoaded($name)) {
            return null;
        }

        $related = $parentModel->getRelation($name);
        $relatedModel = $related instanceof EloquentCollection ? $related->first() : $related;
        if (! $relatedModel instanceof Model) {
            return null;
        }

        try {
            $relatedResource = new $relatedResourceClass($relatedModel);

            return [
                $relatedResource,
                $relatedModel,
                $relatedResource->resolveResourceType(JsonApiRequest::createFrom($request)),
                static::namesFromDeclaration($relatedResource->toAttributes($request)),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Names of the relationships the resource declares.
     *
     * @return string[]
     */
    protected static function declaredRelationships(JsonResource $resource, Request $request): array
    {
        if (! ($element = static::underlyingResource($resource))) {
            return [];
        }

        try {
            // Calling the method also covers resources that declare a `$relationships` property,
            // since the base implementation reads that property for us.
            return static::namesFromDeclaration($element->toRelationships($request));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Names of the attributes the resource declares.
     *
     * @return string[]
     */
    protected static function attributeNames(JsonResource $resource, Request $request): array
    {
        if (! ($element = static::underlyingResource($resource))) {
            return [];
        }

        try {
            return static::namesFromDeclaration($element->toAttributes($request));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * The JSON:API `type` of the resource.
     */
    protected static function resourceType(JsonResource $resource, Request $request): ?string
    {
        if (! ($element = static::underlyingResource($resource))) {
            return null;
        }

        try {
            return $element->resolveResourceType(JsonApiRequest::createFrom($request));
        } catch (\Throwable) {
            // `resolveResourceType()` throws when it can't figure out a type for the resource.
            return null;
        }
    }

    /**
     * The relationships the resource declares, mapped to the resource class handling each one.
     *
     * @return array<string, class-string>
     */
    protected static function declaredRelationshipResources(JsonApiResource $element, Request $request): array
    {
        try {
            $declaration = $element->toRelationships($request);
        } catch (\Throwable) {
            return [];
        }

        $resources = [];
        foreach (static::declarationToArray($declaration) as $key => $value) {
            // Only the `['author' => AuthorResource::class]` style names a resource class;
            // `['tags']` (int key) leaves the framework to derive everything from the model.
            if (is_string($key) && is_string($value) && static::isJsonApiResourceClass($value)) {
                $resources[$key] = $value;
            }
        }

        return $resources;
    }

    /**
     * Relationships and attributes live on the individual resource, not on the collection wrapping it.
     */
    protected static function underlyingResource(JsonResource $resource): ?JsonApiResource
    {
        if (! static::isSupported()) {
            return null;
        }

        if ($resource instanceof JsonApiResource) {
            return $resource;
        }

        if ($resource instanceof JsonApiResourceCollection) {
            $first = $resource->collection->first();

            return $first instanceof JsonApiResource ? $first : null;
        }

        return null;
    }

    /**
     * `toRelationships()` and `toAttributes()` both accept two key styles:
     * `['author' => AuthorResource::class]` (name in the key) and `['tags']` (name in the value).
     *
     * @return string[]
     */
    protected static function namesFromDeclaration(mixed $declaration): array
    {
        $names = [];
        foreach (static::declarationToArray($declaration) as $key => $value) {
            $name = is_int($key) ? $value : $key;
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * `toRelationships()` and `toAttributes()` may hand back an Arrayable or a JsonSerializable
     * rather than a plain array, the same way the framework's own resolvers accept them.
     */
    protected static function declarationToArray(mixed $declaration): array
    {
        if ($declaration instanceof Arrayable) {
            $declaration = $declaration->toArray();
        } elseif ($declaration instanceof \JsonSerializable) {
            $declaration = $declaration->jsonSerialize();
        }

        return is_array($declaration) ? $declaration : [];
    }

    protected static function addParameter(ExtractedEndpointData $endpointData, string $name, Parameter $parameter): bool
    {
        if (isset($endpointData->queryParameters[$name])) {
            return false;
        }

        $endpointData->queryParameters[$name] = $parameter;

        return true;
    }

    /**
     * @param  string[]  $items
     */
    protected static function asCodeList(array $items): string
    {
        return implode(', ', array_map(fn ($item) => "`{$item}`", $items));
    }
}
