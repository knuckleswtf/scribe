<?php

namespace Knuckles\Camel\Extraction;

use Knuckles\Camel\BaseDTOCollection;

/**
 * @extends BaseCollection<Response>
 */
class ResponseCollection extends BaseDTOCollection
{
    public static string $base = Response::class;

    public function current(): Response
    {
        return parent::current();
    }

    public function hasSuccessResponse(): bool
    {
        return collect($this->toArray())
                ->first(function ($response) {
                    return ((string)$response['status'])[0] == '2';
                }) !== null;
    }
}