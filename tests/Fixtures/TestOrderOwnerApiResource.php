<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Knuckles\Scribe\Tests\Fixtures\TestOrderOwner
 */
class TestOrderOwnerApiResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'status' => $this->whenLoaded('status', fn () => [
                'id' => $this->status->id,
                'name' => $this->status->name,
            ]),
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'status' => $this->order->status
                    ? ['id' => $this->order->status->id, 'name' => $this->order->status->name]
                    : null,
                'delivery' => $this->order->delivery ? [
                    'id' => $this->order->delivery->id,
                    'name' => $this->order->delivery->name,
                    'status' => $this->order->delivery->status
                        ? ['id' => $this->order->delivery->status->id, 'name' => $this->order->delivery->status->name]
                        : null,
                    'user' => $this->order->delivery->user
                        ? ['id' => $this->order->delivery->user->id, 'name' => $this->order->delivery->user->name]
                        : null,
                ] : null,
            ]),
        ];
    }
}
