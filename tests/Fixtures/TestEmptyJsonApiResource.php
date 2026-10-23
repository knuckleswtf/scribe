<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * A JSON:API resource with no model to back it. JSON:API responses need an id, so Scribe
 * can't render this one — it should warn rather than take the whole generation down.
 */
class TestEmptyJsonApiResource extends JsonApiResource
{
    public function toAttributes(Request $request): array
    {
        return [];
    }
}
