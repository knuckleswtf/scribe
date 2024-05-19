<?php

namespace Knuckles\Camel\Extraction;

use Knuckles\Camel\BaseDTOCollection;

/**
 * @extends BaseDTOCollection<Response>
 */
class ExampleCollection extends BaseDTOCollection
{
    public static string $base = Example::class;
}