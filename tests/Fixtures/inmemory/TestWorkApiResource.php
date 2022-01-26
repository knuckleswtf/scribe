<?php

namespace Knuckles\Scribe\Tests\Fixtures\inmemory;

use Illuminate\Http\Resources\Json\JsonResource;

class TestWorkApiResource extends JsonResource
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
            'departments' => $this->whenLoaded('departments', function () {
                return TestDepartmentApiResource::collection($this->departments);
            }),
        ];
    }
}
