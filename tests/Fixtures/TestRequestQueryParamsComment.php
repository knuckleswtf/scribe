<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Query parameters
 */
class TestRequestQueryParamsComment extends FormRequest
{
    public function rules()
    {
        return [
            'q_param' => 'int|required',
        ];
    }
}
