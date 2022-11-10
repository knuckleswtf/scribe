<?php

namespace Knuckles\Scribe\Extracting\Strategies\BodyParameters;

use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Extracting\Strategies\GetParamsFromAttributeStrategy;

/**
 * @extends GetParamsFromAttributeStrategy<BodyParam>
 */
class GetFromBodyParamAttribute extends GetParamsFromAttributeStrategy
{
    protected static array $attributeNames = [BodyParam::class];
}
