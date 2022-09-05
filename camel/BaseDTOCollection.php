<?php

namespace Knuckles\Camel;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Spatie\DataTransferObject\DataTransferObjectCollection;

/**
 * @template T of \Spatie\DataTransferObject\DataTransferObject
 */
class BaseDTOCollection extends Collection
{
    /**
     * @var string The name of the base DTO class.
     */
    public static string $base = '';

    public function __construct($items = [])
    {
        // Manually cast nested arrays
        $items = array_map(
            fn($item) => is_array($item) ? new static::$base($item) : $item,
            $items instanceof Collection ? $items->toArray() : $items
        );

        parent::__construct($items);
    }

    /**
     * Append items to the collection, mutating it.
     *
     * @param T[]|array[] $items
     */
    public function concat($items)
    {
        foreach ($items as $item) {
            $this->push(is_array($item) ? new static::$base($item) : $item);
        }
        return $this;
    }

    public function toArray(): array
    {
        return array_map(
            fn($item) => $item instanceof Arrayable ? $item->toArray() : $item,
            $this->items
        );
    }
}
