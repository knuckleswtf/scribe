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
        return $this->first(
                fn($response) => strval($response->status)[0] == '2'
            ) !== null;
    }
}