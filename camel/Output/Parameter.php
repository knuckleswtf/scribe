<?php

namespace Knuckles\Camel\Output;


class Parameter extends \Knuckles\Camel\Extraction\Parameter
{
    public array $__fields = [];

    public function toArray(): array
    {
        if (empty($this->exceptKeys)) {
            return $this->except('__fields')->toArray();
        }

        return parent::toArray();
    }
}
