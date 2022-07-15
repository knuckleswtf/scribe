<?php

namespace Knuckles\Scribe\Extracting\Strategies\BodyParameters;

use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Extracting\Strategies\GetParamsFromAttributeStrategy;

class GetFromBodyParamAttribute extends GetParamsFromAttributeStrategy
{
    protected string $attributeName = BodyParam::class;
}
