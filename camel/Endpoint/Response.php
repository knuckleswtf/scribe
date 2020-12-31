<?php

namespace Knuckles\Camel\Endpoint;



class Response extends BaseDTO
{
    public int $status;
    public ?string $content;
    public ?string $description;
}
