<?php

namespace Knuckles\Scribe\Config;

use Knuckles\Scribe\Extracting\Strategies;

class Defaults
{
    public const METADATA_STRATEGIES = [
        Strategies\Metadata\GetFromDocBlocks::class,
        Strategies\Metadata\GetFromMetadataAttributes::class,
    ];

    public const HEADERS_STRATEGIES = [
        Strategies\Headers\GetFromHeaderAttribute::class,
        Strategies\Headers\GetFromHeaderTag::class,
    ];

    public const URL_PARAMETERS_STRATEGIES = [
        Strategies\UrlParameters\GetFromLaravelAPI::class,
        Strategies\UrlParameters\GetFromUrlParamAttribute::class,
        Strategies\UrlParameters\GetFromUrlParamTag::class,
    ];

    public const QUERY_PARAMETERS_STRATEGIES = [
        Strategies\QueryParameters\GetFromFormRequest::class,
        Strategies\QueryParameters\GetFromInlineValidator::class,
        Strategies\QueryParameters\GetFromQueryParamAttribute::class,
        Strategies\QueryParameters\GetFromQueryParamTag::class,
    ];

    public const BODY_PARAMETERS_STRATEGIES = [
        Strategies\BodyParameters\GetFromFormRequest::class,
        Strategies\BodyParameters\GetFromInlineValidator::class,
        Strategies\BodyParameters\GetFromBodyParamAttribute::class,
        Strategies\BodyParameters\GetFromBodyParamTag::class,
    ];

    public const RESPONSES_STRATEGIES = [
        Strategies\Responses\UseResponseAttributes::class,
        Strategies\Responses\UseTransformerTags::class,
        Strategies\Responses\UseApiResourceTags::class,
        Strategies\Responses\UseResponseTag::class,
        Strategies\Responses\UseResponseFileTag::class,
        Strategies\Responses\ResponseCalls::class,
    ];

    public const RESPONSE_FIELDS_STRATEGIES = [
        Strategies\ResponseFields\GetFromResponseFieldAttribute::class,
        Strategies\ResponseFields\GetFromResponseFieldTag::class,
    ];
}
