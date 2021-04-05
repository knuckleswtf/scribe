<?php

namespace Knuckles\Camel\Extraction;


use Knuckles\Camel\BaseDTO;

class ResponseField extends BaseDTO
{
    /** @var string */
    public $name;

    /** @var string */
    public $description;

    /** @var string */
    public $type;
}
