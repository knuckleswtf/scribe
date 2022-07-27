<?php

namespace Knuckles\Scribe\Extracting\Strategies\UrlParameters;

use Knuckles\Scribe\Attributes\UrlParam;
use Knuckles\Scribe\Extracting\Strategies\GetParamsFromAttributeStrategy;

/**
 * @extends GetParamsFromAttributeStrategy<UrlParam>
 */
class GetFromUrlParamAttribute extends GetParamsFromAttributeStrategy
{
    protected array $attributeNames = [UrlParam::class];
}
