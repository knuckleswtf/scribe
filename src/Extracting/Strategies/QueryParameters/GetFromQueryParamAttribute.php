<?php

namespace Knuckles\Scribe\Extracting\Strategies\QueryParameters;

use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Extracting\Strategies\GetParamsFromAttributeStrategy;

class GetFromQueryParamAttribute extends GetParamsFromAttributeStrategy
{
    protected string $attributeName = QueryParam::class;
}
