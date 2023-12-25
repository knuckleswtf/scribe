<?php

use Knuckles\Scribe\Extracting\Strategies;
use Knuckles\Scribe;

/**
 * For documentation, use your IDE's features, or see https://scribe.knuckles.wtf/laravel/reference/config
 */

return Scribe\Config\Factory::make(
    extracting: Scribe\Config\Extracting::with(
        routes: Scribe\Config\Routes::match(
            prefixes: ['api/*'],
            domains: ['*'],
            alwaysInclude: [],
            alwaysExclude: [],
        ),
        defaultGroup: 'Endpoints',
        databaseConnectionsToTransact: [config('database.default')],
        fakerSeedForExamples: null,
        dataSourcesForExampleModels: ['factoryCreate', 'factoryMake', 'databaseFirst'],
        auth: Scribe\Config\Extracting::auth(
            enabled: false,
            default: false,
            in: 'bearer',
            useValue: env('SCRIBE_AUTH_KEY'),
            placeholder: '{YOUR_AUTH_KEY}',
            extraInfo: <<<AUTH
        You can retrieve your token by visiting your dashboard and clicking <b>Generate API token</b>.
        AUTH
        ),
        strategies: Scribe\Config\Extracting::strategies(
            metadata: Scribe\Config\Defaults::metadataStrategies(),
            urlParameters: Scribe\Config\Defaults::urlParametersStrategies(),
            queryParameters: Scribe\Config\Defaults::queryParametersStrategies(),
            headers: Scribe\Config\Defaults::headersStrategies()
                ->override([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]),
            bodyParameters: Scribe\Config\Defaults::bodyParametersStrategies(),
            responses: Scribe\Config\Defaults::responsesStrategies()
                ->configure(Strategies\Responses\ResponseCalls::withSettings(
                    only: ['GET *'],
                    config: [
                        'app.env' => 'documentation',
                        // 'app.debug' => false,
                    ],
                    queryParams: [],
                    bodyParams: [],
                    fileParams: [],
                    cookies: [],
                )),
            responseFields: Scribe\Config\Defaults::responseFieldsStrategies(),
        )
    ),
    output: Scribe\Config\Output::with(
        theme: 'default',
        title: null,
        description: '',
        baseUrls: [
            "production" => config("app.base_url"),
        ],
        exampleLanguages: ['bash', 'javascript'],
        logo: false,
        lastUpdated: 'Last updated: {date:F j, Y}',
        introText: <<<INTRO
    This documentation aims to provide all the information you need to work with our API.

    <aside>As you scroll, you'll see code examples for working with the API in different programming languages in the dark area to the right (or as part of the content on mobile).
    You can switch the language used with the tabs at the top right (or from the nav menu at the top left on mobile).</aside>
    INTRO,
        groupsOrder: [
            // 'This group will come first',
            // 'This group will come next' => [
            //     'POST /this-endpoint-will-come-first',
            //     'GET /this-endpoint-will-come-next',
            // ],
            // 'This group will come third' => [
            //     'This subgroup will come first' => [
            //         'GET /this-other-endpoint-will-come-first',
            //     ]
            // ]
        ],
        type: Scribe\Config\Output::staticType(
            outputPath: 'public/docs'
        ),
        postman: Scribe\Config\Output::postman(
            enabled: true,
            overrides: [
                // 'info.version' => '2.0.0',
            ]
        ),
        openApi: Scribe\Config\Output::openApi(
            enabled: true,
            overrides: [
                // 'info.version' => '2.0.0',
            ]
        ),
        tryItOut: Scribe\Config\Output::tryItOut(
            enabled: true,
        )
    )
);
