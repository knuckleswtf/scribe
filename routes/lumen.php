<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

$prefix = config('scribe.laravel.docs_url', '/docs');
$middleware = config('scribe.laravel.middleware', []);

$router = app()->router;

$router->group([
    'middleware' => $middleware,
], function () use ($router, $prefix) {
    $router->get($prefix, function () {
        return view('scribe.index');
    })->name('scribe');
    $router->get("$prefix.postman", function () {
        return new JsonResponse(Storage::disk('local')->get('scribe/collection.json'), json: true);
    })->name('scribe.postman');
    $router->get("$prefix.openapi", function () {
        return response()->file(Storage::disk('local')->path('scribe/openapi.yaml'));
    })->name('scribe.openapi');
});
