<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

$prefix = config('scribe.laravel.docs_url', '/docs');
$middleware = config('scribe.laravel.middleware', []);

Route::middleware($middleware)
    ->group(function () use ($prefix) {
        Route::view($prefix, 'scribe.index')->name('scribe');

        Route::get("$prefix.postman", function () {
            return new JsonResponse(
                Storage::disk('local')->get('scribe/collection.json'), json: true
            );
        })->name('scribe.postman');

        Route::get("$prefix.openapi", function () {
            return new BinaryFileResponse(Storage::disk('local')->path('scribe/openapi.yaml'));
        })->name('scribe.openapi');
    });
