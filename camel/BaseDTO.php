<?php

namespace Knuckles\Camel;

use Illuminate\Contracts\Support\Arrayable;
use Spatie\DataTransferObject\DataTransferObject;


class BaseDTO extends DataTransferObject implements Arrayable
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