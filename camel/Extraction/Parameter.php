<?php

namespace Knuckles\Camel\Extraction;


use Knuckles\Camel\BaseDTO;

class Parameter extends BaseDTO
{
    public string $name;
    public ?string $description = null;
    public bool $required = false;
    public $example = null;
    public string $type = 'string';
    public array $custom = [];

    public function __construct(array $parameters = [])
    {
        unset($parameters['setter']);
        parent::__construct($parameters);
    }
}
