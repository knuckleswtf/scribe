<?php

namespace Knuckles\Scribe\Extracting\Strategies\QueryParameters;

use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Extracting\Strategies\GetParamsFromAttributeStrategy;

/**
 * @extends GetParamsFromAttributeStrategy<QueryParam>
 */
class GetFromQueryParamAttribute extends GetParamsFromAttributeStrategy
{
    protected array $attributeNames = [QueryParam::class];
}
