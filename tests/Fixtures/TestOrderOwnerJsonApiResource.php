<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin \Knuckles\Scribe\Tests\Fixtures\TestOrderOwner
 */
class TestOrderOwnerJsonApiResource extends JsonApiResource
{
    public function toType(Request $request): string
    {
        return 'order_owners';
    }

    public function toAttributes(Request $request): array
    {
        return [
            'order_id' => $this->order_id,
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'order' => TestOrderJsonApiResource::class,
        ];
    }
}
