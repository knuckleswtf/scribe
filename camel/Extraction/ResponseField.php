<?php

namespace Knuckles\Camel\Extraction;


use Knuckles\Camel\BaseDTO;

class ResponseField extends BaseDTO
{
    // TODO make this extend Parameter, so we can have strong types and a unified API
    //   but first we need to normalize incoming data

    /** @var string */
    public $name;

    /** @var string */
    public $description;

    /** @var string */
    public $type;

    public array $enumValues = [];
}
