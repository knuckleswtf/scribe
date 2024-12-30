<?php

namespace Knuckles\Scribe\Config;

class Output
{
    public static function with(
        string $theme = 'default',
        ?string $title = null,
        string $description = '',
        array  $baseUrls = [],
        array  $exampleLanguages = ['bash', 'javascript'],
        bool   $logo = false,
        string $lastUpdated = 'Last updated: {date:F j, Y}',
        string $introText = "",
        array  $groupsOrder = [],
        ?array $type = null, /* tuple */
        array  $postman = ['enabled' => true],
        array  $openApi = ['enabled' => true],
        array  $tryItOut = ['enabled' => true],
    ): static
    {
        return new static(...get_defined_vars());
    }

    public function __construct(
        public string  $theme = 'default',
        public ?string $title = null,
        public string  $description = '',
        public array   $baseUrls = [], /* If empty, Scribe will use config('app.url') */
        public array   $groupsOrder = [],
        public string  $introText = "",
        public array   $exampleLanguages = ['bash', 'javascript'],
        public bool    $logo = false,
        public string  $lastUpdated = 'Last updated: {date:F j, Y}',

        public ?array  $type = null, /* tuple */
        public array   $postman = ['enabled' => true],
        public array   $openApi = ['enabled' => true],
        public array   $tryItOut = ['enabled' => true],
    )
    {
    }

    public static function laravelType(
        bool   $addRoutes = true,
        string $docsUrl = '/docs',
        ?string $assetsDirectory = null,
        array  $middleware = [],
    ): array
    {
        return ['laravel', get_defined_vars()];
    }

    public static function staticType(
        string $outputPath = 'public/docs',
    ): array
    {
        return ['static', get_defined_vars()];
    }

    public static function externalStaticType(
        string $outputPath = 'public/docs',
    ): array
    {
        return ['external_static', get_defined_vars()];
    }

    public static function externalLaravelType(
        bool   $addRoutes = true,
        string $docsUrl = '/docs',
        array  $middleware = [],
    ): array
    {
        return ['external_laravel', get_defined_vars()];
    }

    public static function postman(
        bool  $enabled = true,
        array $overrides = [],
    ): array
    {
        return get_defined_vars();
    }

    public static function openApi(
        bool  $enabled = true,
        array $overrides = [],
    ): array
    {
        return get_defined_vars();
    }

    public static function tryItOut(
        bool   $enabled = true,
        ?string $baseUrl = null,
        bool   $useCsrf = false,
        string $csrfUrl = '/sanctum/csrf-cookie',
    ): array
    {
        return get_defined_vars();
    }
}
