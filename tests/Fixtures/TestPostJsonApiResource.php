<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin \Knuckles\Scribe\Tests\Fixtures\TestPost
 */
class TestPostJsonApiResource extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'tags' => TestTagJsonApiResource::class,
        ];
    }
}
