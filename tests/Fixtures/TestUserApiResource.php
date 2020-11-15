<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

class TestUserApiResource extends JsonResource
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
            'name' => $this->first_name . ' ' . $this->last_name,
            'email' => $this->email,
            'children' => $this->whenLoaded('children', function () {
                return TestUserApiResource::collection($this->children);
            }),
        ];

        if($request->route()->named('someone')) {
            return ['someone' => true];
        }

        if ($this['state1'] && $this['random-state']) {
            $result['state1'] = $this['state1'];
            $result['random-state'] = $this['random-state'];
        }

        return $result;
    }
}
