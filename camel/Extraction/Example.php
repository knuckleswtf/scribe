<?php

namespace Knuckles\Camel\Extraction;


use Knuckles\Camel\BaseDTO;

class Example extends BaseDTO
{
    public ?string $type;

    public array $meta;

    public ?string $content;

    public ?string $description;

    public function __construct(array $parameters = [])
    {
        if (is_array($parameters['type'] ?? null)) {
            $parameters['type'] = $parameters['type'];
        }

        $parameters['meta'] = $parameters['meta'] ?? [];
        
        if (is_array($parameters['content'] ?? null)) {
            $parameters['content'] = json_encode($parameters['content'], JSON_UNESCAPED_SLASHES);
        }

        parent::__construct($parameters);
    }

    public function fullDescription()
    {
        return $this->description;
    }
}
