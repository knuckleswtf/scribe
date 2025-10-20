<?php

namespace Knuckles\Camel;

use Illuminate\Contracts\Support\Arrayable;


class BaseDTO implements Arrayable, \ArrayAccess
{
    /**
     * @var array $custom
     * Added so end-users can dynamically add additional properties for their own use.
     */
    public array $custom = [];

    public function __construct(array $parameters = [])
    {
        // Initialize all properties to their default values first
        $this->initializeProperties();
        
        foreach ($parameters as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $this->castProperty($key, $value);
            }
        }
    }
    
    protected function initializeProperties(): void
    {
        $reflection = new \ReflectionClass($this);
        
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            
            // Skip if already initialized (has a default value)
            if ($property->hasDefaultValue()) {
                continue;
            }
            
            $type = $property->getType();
            if ($type && $type->allowsNull()) {
                $this->$name = null;
            }
        }
    }

    protected function castProperty(string $key, mixed $value): mixed
    {
        // If the value is already the correct type, return it as-is
        if (!is_array($value)) {
            return $value;
        }

        // Get property type through reflection
        $reflection = new \ReflectionClass($this);
        if (!$reflection->hasProperty($key)) {
            return $value;
        }

        $property = $reflection->getProperty($key);
        $type = $property->getType();
        
        if ($type && $type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            $className = $type->getName();
            
            // If it's a DTO class in our namespace, instantiate it
            if (class_exists($className) && is_subclass_of($className, self::class)) {
                return new $className($value);
            }
            
            // If it's another class in our namespace that has a constructor accepting arrays
            if (class_exists($className)) {
                try {
                    return new $className($value);
                } catch (\Throwable $e) {
                    // If instantiation fails, return the original value
                    return $value;
                }
            }
        }
        
        return $value;
    }

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

    public function toArray(): array
    {
        $array = [];
        foreach (get_object_vars($this) as $property => $value) {
            $array[$property] = $value;
        }
        return $this->parseArray($array);
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
    
    public function except(string ...$keys): array
    {
        $array = [];
        foreach (get_object_vars($this) as $property => $value) {
            if (!in_array($property, $keys)) {
                $array[$property] = $value;
            }
        }
        return $this->parseArray($array);
    }
    
    public static function arrayOf(array $items): array
    {
        return array_map(function ($item) {
            return $item instanceof static ? $item : new static($item);
        }, $items);
    }
}
