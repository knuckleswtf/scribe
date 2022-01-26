<?php

namespace Knuckles\Scribe\Tests\Fixtures\inmemory;

use Illuminate\Http\Resources\Json\JsonResource;

class TestPetApiResource extends JsonResource
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
        $result = [
            'id' => $this->id,
            'name' => $this->name,
            'species' => $this->species,
            'owners' => $this->whenLoaded('owners', function () {
                return TestUserApiResource::collection($this->owners);
            }),
            'ownership' => $this->whenPivotLoaded('pet_user', function () {
                return $this->pivot;
            })
        ];

        return $result;
    }
}
