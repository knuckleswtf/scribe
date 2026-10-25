<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin \Knuckles\Scribe\Tests\Fixtures\TestOrderStatus
 */
class TestOrderStatusJsonApiResource extends JsonApiResource
{
    public function toType(Request $request): string
    {
        return 'order_statuses';
    }

    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
        ];
    }
}
