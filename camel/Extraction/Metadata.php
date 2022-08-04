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

    /**
     * Name of the group that this group should be placed just before.
     * Only used in custom endpoints, if the endpoint's `groupName` doesn't already exist.
     */
    public ?string $beforeGroup;

    /**
     * Name of the group that this group should be placed just after.
     * Only used in custom endpoints, if the endpoint's `groupName` doesn't already exist.
     */
    public ?string $afterGroup;
}
