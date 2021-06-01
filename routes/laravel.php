<?php

use Illuminate\Support\Facades\Route;
use Knuckles\Scribe\Http\Controller;

$prefix = config('scribe.laravel.docs_url', '/docs');
$middleware = config('scribe.laravel.middleware', []);

Route::middleware($middleware)
    ->group(function () use ($prefix) {
        Route::get($prefix, [Controller::class, 'webpage'])->name('scribe');
        Route::get("$prefix.postman", [Controller::class, 'postman'])->name('scribe.postman');
        Route::get("$prefix.openapi", [Controller::class, 'openapi'])->name('scribe.openapi');
    });
