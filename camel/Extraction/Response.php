<?php

namespace Knuckles\Camel\Extraction;



use Knuckles\Camel\BaseDTO;

class Response extends BaseDTO
{
    public int $status;
    public ?string $content;
    public ?string $description;
}
