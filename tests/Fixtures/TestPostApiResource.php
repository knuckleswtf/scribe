<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

class TestPostApiResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title ,
            'body' => $this->body,
            'tags' => $this->whenLoaded('tags', function () {
                return TestTagApiResource::collection($this->tags);
            }),
        ];
    }
}
