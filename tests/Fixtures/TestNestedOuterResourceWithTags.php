<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

class TestNestedOuterResourceWithTags extends JsonResource
{
    /**
     * Create a new resource instance.
     *
     * @param mixed $resource
     */
    public function __construct($resource = [])
    {
        $this->resource = $resource;
    }

    /**
     * Transform the resource into an array.
     *
     * @responseField outer1 string required First outer string
     * @responseField outer1.inner1 string required First inner string
     * @responseField outer2 string required Second outer string
     * @responseField outer2.inner2 string required Second inner string
     *
     * @param mixed $request
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'outer1' => [
                'inner1' => 'string',
            ],
            'outer2' => new TestNestedInnerResourceWithTags($this->resource),
        ];
    }
}
