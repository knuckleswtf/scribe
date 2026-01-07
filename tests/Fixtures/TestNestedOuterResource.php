<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;

class TestNestedOuterResource extends JsonResource
{
    /**
     * Create a new resource instance.
     *
     * @param  mixed  $resource
     * @return void
     */
    public function __construct($resource = [])
    {
        $this->resource = $resource;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[
        ResponseField('outer1', required: true),
        ResponseField('outer1.inner1', required: true),
        ResponseField('outer2', required: true),
        ResponseField('outer2.inner2', required: true),
    ]
    public function toArray($request)
    {
        return [
            'outer1' => [
                'inner1' => 'string',
            ],
            'outer2' => new TestNestedInnerResource($this->resource),
        ];
    }
}
