<?php

namespace Knuckles\Camel\Extraction;


use Knuckles\Camel\BaseDTO;

class Parameter extends BaseDTO
{
    /** @var string */
    public $name;
    /** @var string|null */
    public $description = null;
    /** @var bool */
    public $required = false;
    public $example = null;
    /** @var string */
    public $type = 'string';
}
