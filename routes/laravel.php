<?php

use Illuminate\Support\Facades\Route;

$prefix = config('scribe.laravel.docs_url', '/docs');
$middleware = config('scribe.laravel.middleware', []);

Route::namespace('\Knuckles\Scribe\Http')
    ->middleware($middleware)
    ->group(function () use ($prefix) {
        Route::get($prefix, 'Controller@webpage')->name('scribe');
        Route::get("$prefix.postman", 'Controller@postman')->name('scribe.postman');
        Route::get("$prefix.openapi", 'Controller@openapi')->name('scribe.openapi');
    });
