<?php


namespace Knuckles\Scribe\Extracting;


use Illuminate\Contracts\Validation\Rule;

/**
 * BodyParameter validation rule
 * This is a dummy rule that always passes.
 * Used to pass properties of the body parameter to this package.
 */
class BodyParameterDefinition implements Rule
{
    /**
     * @var string
     */
    public $parameterDescription;

    public $parameterExample;

    public const RULENAME = "scribe_body_param";
    public const DELIMITER = "/////";

    public function __construct(string $description = '', $example = '')
    {
        $this->parameterDescription = $description;
        $this->parameterExample = $example;
    }

    public function passes($attribute, $value)
    {
        return true;
    }

    public function message()
    {
        return '';
    }

    public function __toString()
    {
        // Can't use comma as delimiter since description may contain a comma
        return self::RULENAME.":{$this->parameterDescription}".self::DELIMITER."$this->parameterExample";
    }
}
