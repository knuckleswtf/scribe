<?php

use Illuminate\Support\Facades\Route;

$prefix = config('scribe.laravel.docs_url', '/docs');
$middleware = config('scribe.laravel.middleware', []);

$router = app()->router;

$router->group([
    'namespace' => '\Knuckles\Scribe\Http',
    'middleware' => $middleware
], function () use ($router, $prefix) {
    $router->get($prefix, ['uses' => 'Controller@webpage', 'as' => 'scribe']);
    $router->get("$prefix.postman", ['uses' => 'Controller@postman', 'as' => 'scribe.postman']);
    $router->get("$prefix.openapi", ['uses' => 'Controller@openapi', 'as' => 'scribe.openapi']);
});
