<?php

namespace Knuckles\Camel\Endpoint;

use Spatie\DataTransferObject\DataTransferObjectCollection;

/**
 * @template T of \Spatie\DataTransferObject
 */
class BaseDTOCollection extends DataTransferObjectCollection
{
    /**
     * @var class-string<T> The name of the base DTO class.
     */
    public static string $base = '';

    public function __construct(array $collection = [])
    {
        // Manually cast nested arrays
        $collection = array_map(
            fn($item) => is_array($item) ? new static::$base($item) : $item,
            $collection
        );

        parent::__construct($collection);
    }

    /**
     * @param T[] $items
     */
    public function concat($items)
    {
        foreach ($items as $item) {
            $this[] = is_array($item) ? new static::$base($item) : $item;
        }
    }
}