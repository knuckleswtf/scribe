<?php

namespace Knuckles\Camel\Endpoint;


class Metadata extends BaseDTO
{
    public ?string $groupName;

    public ?string $groupDescription;

    public ?string $title;

    public ?string $description;

    public bool $authenticated = false;
}