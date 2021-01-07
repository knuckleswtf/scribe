<?php

namespace Knuckles\Camel\Output;


use Illuminate\Support\Arr;
use Knuckles\Camel\BaseDTO;

class Group extends BaseDTO
{
    public string $name;
    public ?string $description;

    /**
     * @var \Knuckles\Camel\Output\EndpointData[] $endpoints
     */
    public array $endpoints = [];

    public static function createFromSpec(array $spec): Group
    {
        $spec['endpoints'] = array_map(
            fn($endpoint) => new EndpointData($endpoint), $spec['endpoints']
        );
        return new Group($spec);
    }

    public function has(EndpointData $endpoint)
    {
        return boolval(Arr::first($this->endpoints, fn(EndpointData $e) => $e->endpointId() === $endpoint->endpointId()));
    }
}
