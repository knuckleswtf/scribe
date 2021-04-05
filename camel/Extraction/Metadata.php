<?php

namespace Knuckles\Camel\Extraction;


use Knuckles\Camel\BaseDTO;

class Metadata extends BaseDTO
{
    /** @var string|null */
    public $groupName;

    /** @var string|null */
    public $groupDescription;

    /** @var string|null */
    public $title;

    /** @var string|null */
    public $description;

    /** @var bool */
    public $authenticated = false;
}