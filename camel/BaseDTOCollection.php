<?php

namespace Knuckles\Camel;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Illuminate\Support\Arr;
use Iterator;
use Spatie\DataTransferObject\DataTransferObject;

/**
 * @template T of \Spatie\DataTransferObject\DataTransferObject
 */
abstract class BaseDTOCollection implements
    ArrayAccess,
    Iterator,
    Countable
{
    protected ArrayIterator $iterator;

    /**
     * @var string The name of the base DTO class.
     */
    public static string $base = '';

    /**
     * @param BaseDTOCollection|array $collection
     */
    public function __construct($collection = [])
    {
        if (is_null($collection)) $collection = [];
        if (!is_array($collection)) $collection = $collection->toArray();

        // Manually cast nested arrays
        $collection = array_map(
            function ($item) {
                return is_array($item) ? new static::$base($item) : $item;
            },
            $collection
        );

        $this->iterator = new ArrayIterator($collection);
    }

    public function __get($name)
    {
        if ($name === 'collection') {
            return $this->iterator->getArrayCopy();
        }
    }

    public function current()
    {
        return $this->iterator->current();
    }

    public function offsetGet($offset)
    {
        return $this->iterator[$offset] ?? null;
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->iterator[] = $value;
        } else {
            $this->iterator[$offset] = $value;
        }
    }

    public function offsetExists($offset): bool
    {
        return $this->iterator->offsetExists($offset);
    }

    public function offsetUnset($offset)
    {
        unset($this->iterator[$offset]);
    }

    public function next()
    {
        $this->iterator->next();
    }

    public function key()
    {
        return $this->iterator->key();
    }

    public function valid(): bool
    {
        return $this->iterator->valid();
    }

    public function rewind()
    {
        $this->iterator->rewind();
    }

    public function toArray(): array
    {
        $collection = $this->iterator->getArrayCopy();

        foreach ($collection as $key => $item) {
            if (
                ! $item instanceof DataTransferObject
                && ! $item instanceof BaseDTOCollection
            ) {
                continue;
            }

            $collection[$key] = $item->toArray();
        }

        return $collection;
    }

    public function items(): array
    {
        return $this->iterator->getArrayCopy();
    }

    public function count(): int
    {
        return count($this->iterator);
    }

    /**
     * @param T[]|array[] $items
     */
    public function concat(array $items)
    {
        foreach ($items as $item) {
            $this[] = is_array($item) ? new static::$base($item) : $item;
        }
    }

    /**
     * @param string $key
     */
    public function sortBy(string $key): void
    {
        $items = $this->items();
        $items = Arr::sort($items, $key);
        $this->iterator = new ArrayIterator(array_values($items));
    }
}
