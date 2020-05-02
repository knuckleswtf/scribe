<?php

namespace Knuckles\Scribe\Extracting;

class ValidationRuleDescriptionParser
{
    private $rule;

    private $arguments = [];

    const DEFAULT_LOCALE = 'en';

    /**
     * @param $rule
     */
    public function __construct($rule = null)
    {
        $this->rule = $rule;
    }

    /**
     * @return array|string
     */
    public static function getDescription(string $rule, $arguments = [], $type = 'string')
    {
        $instance = new static($rule);

        $instance->arguments = $arguments;

        return $instance->makeDescription($type);
    }

    /**
     * @return string
     */
    protected function makeDescription($type = 'string')
    {
        $description = trans("validation.{$this->rule}");
        // For rules that can apply to multiple types (eg 'max' rule), Laravel returns an array of possible messages
        // 'numeric' => 'The :attribute must not be greater than :max'
        // 'file' => 'The :attribute must have a size less than :max kilobytes'
        if (is_array($description)) {
            $description = $description[$type];
        }

        // Convert messages from failure type ("The value is not a valid date.") to info ("The value must be a valid date.")
        $description = str_replace(['is not', 'does not'], ['must be', 'must'], $description);

        return $this->replaceArguments($description);
    }

    /**
     * @param string $description$
     *
     * @return string
     */
    protected function replaceArguments($description)
    {
        foreach ($this->arguments as $placeholder => $argument) {
            $description = str_replace($placeholder, $argument, $description);
        }

        $description = str_replace("The :attribute", "The value", $description);

        return $description;
    }
}
