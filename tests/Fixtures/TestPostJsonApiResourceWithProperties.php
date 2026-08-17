<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * Declares its attributes and relationships as properties rather than by overriding
 * `toAttributes()`/`toRelationships()`, and using the int-key style for both.
 *
 * @mixin \Knuckles\Scribe\Tests\Fixtures\TestPost
 */
class TestPostJsonApiResourceWithProperties extends JsonApiResource
{
    protected array $attributes = ['title', 'body'];

    protected array $relationships = ['tags'];
}
