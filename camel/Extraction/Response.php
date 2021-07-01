<?php

namespace Knuckles\Camel\Extraction;


use Knuckles\Camel\BaseDTO;

class Response extends BaseDTO
{
    public int $status;

    public ?string $content;

    public array $headers = [];

    public ?string $description;

    public function __construct(array $parameters = [])
    {
        if (is_array($parameters['content'])) {
            $parameters['content'] = json_encode($parameters['content']);
        }

        $hiddenHeaders = [
            'date',
            'Date',
            'etag',
            'ETag',
            'last-modified',
            'Last-Modified',
            'date',
            'Date',
            'content-length',
            'Content-Length',
            'connection',
            'Connection',
            'x-powered-by',
            'X-Powered-By',
        ];
        if (!empty($parameters['headers'])) {
            foreach ($hiddenHeaders as $headerName) {
                unset($parameters['headers'][$headerName]);
            }
        }

        return parent::__construct($parameters);
    }
}
