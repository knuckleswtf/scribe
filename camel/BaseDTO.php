<?php

namespace Knuckles\Camel;

use Spatie\DataTransferObject\DataTransferObject;


class BaseDTO extends DataTransferObject
{
    /**
     * @param array|self $data
     *
     * @return static
     */
    public static function create($data): self
    {
        if ($data instanceof static) {
            return $data;
        }

        return new static($data);
    }
}