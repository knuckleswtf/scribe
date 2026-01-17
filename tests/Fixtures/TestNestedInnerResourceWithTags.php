<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

class TestNestedInnerResourceWithTags extends JsonResource
{
    /**
     * Create a new resource instance.
     *
     * @param  mixed  $resource
     */
    public function __construct($resource = [])
    {
        $this->resource = $resource;
    }

    /**
     * Transform the resource into an array.
     *
     * @param  mixed  $request
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'inner2' => 'string',
        ];
    }
}
