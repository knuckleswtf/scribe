<?php

use Knuckles\Scribe\Http\Controller;

$prefix = config('scribe.laravel.docs_url', '/docs');
$middleware = config('scribe.laravel.middleware', []);

$router = app()->router;

$router->group([
    'middleware' => $middleware
], function () use ($router, $prefix) {
    $router->get($prefix, ['uses' => [Controller::class, 'webpage'], 'as' => 'scribe']);
    $router->get("$prefix.postman", ['uses' => [Controller::class, 'postman'], 'as' => 'scribe.postman']);
    $router->get("$prefix.openapi", ['uses' => [Controller::class, 'openapi'], 'as' => 'scribe.openapi']);
});
