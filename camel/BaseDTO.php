<?php

namespace Knuckles\Camel;

use Illuminate\Contracts\Support\Arrayable;
use Spatie\DataTransferObject\DataTransferObject;


class BaseDTO extends DataTransferObject implements Arrayable, \ArrayAccess
{
    /**
     * @var array $custom
     * Added so end-users can dynamically add additional properties for their own use.
     */
    public array $custom = [];

    public static function create(BaseDTO|array $data, BaseDTO|array $inheritFrom = []): static
    {
        if ($data instanceof static) {
            return $data;
        }

        $mergedData = $inheritFrom instanceof static ? $inheritFrom->toArray() : $inheritFrom;

        foreach ($data as $property => $value) {
            $mergedData[$property] = $value;
        }

        return new static($mergedData);
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

    public static function make(array|self $data): static
    {
        return $data instanceof static ? $data : new static($data);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->$offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->$offset;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->$offset = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->$offset);
    }
}
