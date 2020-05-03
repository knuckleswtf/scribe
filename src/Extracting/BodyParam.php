<?php

namespace Knuckles\Scribe\Extracting;


class BodyParam
{
    /**
     * @var string
     */
    private $description;

    private $example;

    /**
     * @var array
     */
    private $validationRules = [];

    public function __construct(string $description = '', $example = '')
    {
        $this->description = $description;
        $this->example = $example;
    }

    public static function description(string $description = '')
    {
        return new self($description);
    }

    public function example($example = null)
    {
        $this->example = $example;
        return $this;
    }

    public function rules($rules = [])
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        $rules[] = (new BodyParameterDefinition($this->description, $this->example))->__toString();
        $this->validationRules = $rules;
        return $rules;
    }

    public function __toString()
    {
        return implode('|', array_map(function ($rule) {
            return (string) $rule; // Cast is important to handle rule objects
        }, $this->validationRules));
    }
}
