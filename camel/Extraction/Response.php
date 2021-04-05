<?php

namespace Knuckles\Camel\Extraction;



use Knuckles\Camel\BaseDTO;

class Response extends BaseDTO
{
    /** @var int */
    public $status;

    /**
     * @var string|null
     */
    public $content;

    /**
     * @var array
     */
    public $headers = [];

    /**
     * @var string|null
     */
    public $description;

    public function __construct(array $parameters = [])
    {
        if (is_array($parameters['content'])) {
            $parameters['content'] = json_encode($parameters['content']);
        }
        if (!empty($parameters['headers'])) {
            unset($parameters['headers']['date']);
            unset($parameters['headers']['Date']);
        }

        return parent::__construct($parameters);
    }
}
