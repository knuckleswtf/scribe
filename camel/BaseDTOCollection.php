<?php

namespace Knuckles\Camel;

use ArrayIterator;
use Illuminate\Support\Arr;
use Knuckles\Camel\Extraction\T;
use Spatie\DataTransferObject\DataTransferObjectCollection;

/**
 * @template T of \Spatie\DataTransferObject
 */
class BaseDTOCollection extends DataTransferObjectCollection
{
    /**
     * @var string The name of the base DTO class.
     */
    public static string $base = '';

    public function __construct(array $collection = [])
    {
        // Manually cast nested arrays
        $collection = array_map(
            function ($item) {
                return is_array($item) ? new static::$base($item) : $item;
            },
            $collection
        );

        parent::__construct($collection);
    }

    /**
     * @param T[] $items
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