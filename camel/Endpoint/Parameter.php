<?php

namespace Knuckles\Camel\Endpoint;


class Parameter extends BaseDTO
{
    public string $name;
    public ?string $description = null;
    public bool $required = false;
    public $value = null;
    public string $type = 'string';
    public array $__fields = [];
}
