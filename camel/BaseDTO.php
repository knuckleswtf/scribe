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

    protected function parseArray(array $array): array
    {
        // Reimplementing here so our DTOCollection items can be recursively toArray'ed
        foreach ($array as $key => $value) {
            if ($value instanceof Arrayable) {
                $array[$key] = $value->toArray();

                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            $array[$key] = $this->parseArray($value);
        }

        return $array;
    }
}