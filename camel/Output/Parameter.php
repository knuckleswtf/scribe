<?php

namespace Knuckles\Camel\Output;


class Parameter extends \Knuckles\Camel\Extraction\Parameter
{
    public string $name;

    public ?string $description = null;

    public bool $required = false;

    public $example = null;

    public string $type = 'string';

    public array $__fields = [];

    public array $custom = [];

    public function toArray(): array
    {
        if (empty($this->exceptKeys)) {
            return $this->except('__fields')->toArray();
        }

        return parent::toArray();
    }
}
