<?php

namespace Knuckles\Camel\Extraction;

use Knuckles\Camel\BaseDTOCollection;

/**
 * @extends BaseDTOCollection<Response>
 */
class ResponseCollection extends BaseDTOCollection
{
    public static string $base = Response::class;

    public function hasSuccessResponse(): bool
    {
        return null !== $this->first(
            fn ($response) => '2' == strval($response->status)[0]
        );
    }
}
