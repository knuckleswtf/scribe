<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @queryParam location_id required The id of the location.
 * @queryParam user_id required The id of the user. Example: me
 * @queryParam page required The page number. Example: 4
 * @queryParam url_encoded  Used for testing that URL parameters will be URL-encoded where needed. Example: + []&=
 * @bodyParam user_id int required The id of the user. Example: 9
 * @bodyParam room_id string The id of the room.
 * @bodyParam forever boolean Whether to ban the user forever. Example: false
 * @bodyParam another_one number Just need something here.
 * @bodyParam yet_another_param object required
 * @bodyParam even_more_param string[]
 * @bodyParam book.name string
 * @bodyParam book.author_id integer
 * @bodyParam book.pages_count integer
 * @bodyParam ids integer[]
 * @bodyParam users object[] User details
 * @bodyParam users[].first_name string The first name of the user. Example: John
 * @bodyParam users[].last_name string The last name of the user. Example: Doe
 */
class TestRequest extends FormRequest
{
    public function rules()
    {
        return [
            'user_id' => 'int|required',
            'room_id' => ['string'],
            'forever' => 'boolean',
            'another_one' => 'numeric',
            'even_more_param' => 'array',
            'book.name' => 'string',
            'book.author_id' => 'integer',
            'book.pages_count' => 'integer',
            'ids.*' => 'integer',
            'users.*.first_name' => ['string'],
            'users.*.last_name' => 'string',
        ];
    }

    public function bodyParameters()
    {
        return [
            'user_id' => [
                'description' => 'The id of the user.',
                'example' => 9,
            ],
            'room_id' => [
                'description' => 'The id of the room.',
            ],
            'forever' => [
                'description' => 'Whether to ban the user forever.',
                'example' => false,
            ],
            'another_one' => [
                'description' => 'Just need something here.',
            ],
            'even_more_param' => [
                'description' => '',
            ],
            'book.name' => [
                'description' => '',
            ],
            'users.*.first_name' => [
                'description' => 'The first name of the user.',
                'example' => 'John',
            ],
            'users.*.last_name' => [
                'description' => 'The last name of the user.',
                'example' => 'Doe',
            ],
        ];
    }
}
