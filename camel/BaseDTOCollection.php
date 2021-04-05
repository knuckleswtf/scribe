<?php

namespace Knuckles\Camel;

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
    public static $base = '';

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
    public function concat($items)
    {
        foreach ($items as $item) {
            $this[] = is_array($item) ? new static::$base($item) : $item;
        }
    }
}