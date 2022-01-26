<?php

namespace Knuckles\Scribe\Tests\Fixtures\inmemory;

use Illuminate\Http\Resources\Json\JsonResource;

class TestDepartmentApiResource extends JsonResource
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
            'name' => $this->name,
        ];
    }
}
