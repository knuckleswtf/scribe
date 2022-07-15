<?php

namespace Knuckles\Scribe\Extracting\Strategies\UrlParameters;

use Knuckles\Scribe\Attributes\UrlParam;
use Knuckles\Scribe\Extracting\Strategies\GetParamsFromAttributeStrategy;

class GetFromUrlParamAttribute extends GetParamsFromAttributeStrategy
{
    protected string $attributeName = UrlParam::class;
}
