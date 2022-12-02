<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

class TestImageApiResource extends JsonResource
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
            'url' => $this->url ,
        ];
    }
}
