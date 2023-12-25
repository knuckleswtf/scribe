<?php

namespace Knuckles\Scribe\Config;

use Knuckles\Scribe\Extracting\Strategies;

class Defaults
{
    public static function metadataStrategies(): StrategyListWrapper
    {
        return new StrategyListWrapper([
            Strategies\Metadata\GetFromDocBlocks::class,
            Strategies\Metadata\GetFromMetadataAttributes::class,
        ]);
    }

    public static function urlParametersStrategies(): StrategyListWrapper
    {
        return new StrategyListWrapper([
            Strategies\UrlParameters\GetFromLaravelAPI::class,
            Strategies\UrlParameters\GetFromLumenAPI::class,
            Strategies\UrlParameters\GetFromUrlParamAttribute::class,
            Strategies\UrlParameters\GetFromUrlParamTag::class,
        ]);
    }

    public static function queryParametersStrategies(): StrategyListWrapper
    {
        return new StrategyListWrapper([
            Strategies\QueryParameters\GetFromFormRequest::class,
            Strategies\QueryParameters\GetFromInlineValidator::class,
            Strategies\QueryParameters\GetFromQueryParamAttribute::class,
            Strategies\QueryParameters\GetFromQueryParamTag::class,
        ]);
    }

    public static function headersStrategies(): StrategyListWrapper
    {
        return new StrategyListWrapper([
            Strategies\Headers\GetFromRouteRules::class,
            Strategies\Headers\GetFromHeaderAttribute::class,
            Strategies\Headers\GetFromHeaderTag::class,
        ]);
    }

    public static function bodyParametersStrategies(): StrategyListWrapper
    {
        return new StrategyListWrapper([
            Strategies\BodyParameters\GetFromFormRequest::class,
            Strategies\BodyParameters\GetFromInlineValidator::class,
            Strategies\BodyParameters\GetFromBodyParamAttribute::class,
            Strategies\BodyParameters\GetFromBodyParamTag::class,
        ]);
    }

    public static function responsesStrategies(): StrategyListWrapper
    {
        return new StrategyListWrapper([
            Strategies\Responses\UseResponseAttributes::class,
            Strategies\Responses\UseTransformerTags::class,
            Strategies\Responses\UseApiResourceTags::class,
            Strategies\Responses\UseResponseTag::class,
            Strategies\Responses\UseResponseFileTag::class,
            Strategies\Responses\ResponseCalls::class,
        ]);
    }

    public static function responseFieldsStrategies(): StrategyListWrapper
    {
        return new StrategyListWrapper([
            Strategies\ResponseFields\GetFromResponseFieldAttribute::class,
            Strategies\ResponseFields\GetFromResponseFieldTag::class,
        ]);
    }

}
