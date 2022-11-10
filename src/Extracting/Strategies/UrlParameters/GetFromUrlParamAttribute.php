<?php

namespace Knuckles\Scribe\Extracting\Strategies\UrlParameters;

use Knuckles\Scribe\Attributes\UrlParam;
use Knuckles\Scribe\Extracting\Strategies\GetParamsFromAttributeStrategy;

/**
 * @extends GetParamsFromAttributeStrategy<UrlParam>
 */
class GetFromUrlParamAttribute extends GetParamsFromAttributeStrategy
{
    protected static array $attributeNames = [UrlParam::class];
}
