<?php

namespace Knuckles\Camel\Extraction;



use Knuckles\Camel\BaseDTO;

class Response extends BaseDTO
{
    public int $status;

    /**
     * @var string|null
     */
    public $content;
    public ?string $description;

    public function __construct(array $parameters = [])
    {
        if (is_array($parameters['content'])) {
            $parameters['content'] = json_encode($parameters['content']);
        }

        return parent::__construct($parameters);
    }
}
