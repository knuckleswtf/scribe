<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin \Knuckles\Scribe\Tests\Fixtures\TestOrder
 */
class TestOrderJsonApiResource extends JsonApiResource
{
    public function toType(Request $request): string
    {
        return 'orders';
    }

    public function toAttributes(Request $request): array
    {
        return [
            'status_id' => $this->status_id,
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'status' => TestOrderStatusJsonApiResource::class,
        ];
    }
}
