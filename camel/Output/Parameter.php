<?php

namespace Knuckles\Camel\Output;


class Parameter extends \Knuckles\Camel\Extraction\Parameter
{
    /** @var string */
    public $name;

    /** @var string|null */
    public $description = null;

    /** @var bool */
    public $required = false;

    public $example = null;

    /** @var string */
    public $type = 'string';

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
