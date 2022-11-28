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
        if (is_array($parameters['content'] ?? null)) {
            $parameters['content'] = json_encode($parameters['content'], JSON_UNESCAPED_SLASHES);
        }

        if (isset($parameters['status'])) {
            $parameters['status'] = (int) $parameters['status'];
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

        parent::__construct($parameters);
    }

    public function fullDescription()
    {
        $description = $this->status;
        if ($this->description) $description .= ", {$this->description}";
        return $description;
    }
}
