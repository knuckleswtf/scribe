<?php

namespace Knuckles\Camel\Extraction;

use Knuckles\Camel\BaseDTO;

class Metadata extends BaseDTO
{
    public ?string $groupName;
    public ?string $groupDescription;
    public ?string $subgroup;
    public ?string $subgroupDescription;
    public ?string $title;
    public ?string $description;
    public bool $authenticated = false;
}
