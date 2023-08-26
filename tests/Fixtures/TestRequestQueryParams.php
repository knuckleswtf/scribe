<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;

class TestRequestQueryParams extends FormRequest
{
    public function rules(\stdClass $injectedDep)
    {
        return [
            'q_param' => 'int|required',
        ];
    }

    public function queryParameters()
    {
        return [
            'q_param' => [
                'description' => 'The param.',
                'example' => 9,
            ],
        ];
    }
}
