<?php

namespace Knuckles\Camel\Output;


class Parameter extends \Knuckles\Camel\Extraction\Parameter
{
    /** @var string */
    public string $name;

    /** @var string|null */
    public ?string $description = null;

    /** @var bool */
    public bool $required = false;

    public $example = null;

    /** @var string */
    public string $type = 'string';

    /** @var array */
    public $__fields = [];

    public function toArray(): array
    {
        if (empty($this->exceptKeys)) {
            return $this->except('__fields')->toArray();
        }

        return parent::toArray();
    }
}
