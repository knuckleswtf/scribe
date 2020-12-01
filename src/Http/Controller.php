<?php

namespace Knuckles\Scribe\Http;

use Illuminate\Support\Facades\Storage;

class Controller
{
    public function webpage()
    {
        return view('scribe.index');
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function postman()
    {
        return response()->json(
            json_decode(Storage::disk('local')->get('scribe/collection.json'))
        );
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function openapi()
    {
        return response()->file(Storage::disk('local')->path('scribe/openapi.yaml'));
    }
}
