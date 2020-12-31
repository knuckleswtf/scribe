<?php

namespace Knuckles\Camel\Endpoint;

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

    public function hasSuccessResponse()
    {
        return collect($this->toArray())
                ->first(fn($response) => ((string)$response['status'])[0] == '2') !== null;
    }
}