<?php

use Illuminate\Support\Facades\Route;

$prefix = config('scribe.laravel.docs_url', '/docs');
$middleware = config('scribe.laravel.middleware', []);

Route::namespace('\Knuckles\Scribe\Http')
    ->middleware($middleware)
    ->group(function () use ($prefix) {
        Route::get($prefix, 'Controller@html')->name('scribe');
        Route::get("$prefix.json", 'Controller@json')->name('scribe.json');
    });
