<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Extracting\BodyParam;

/**
 * @queryParam location_id required The id of the location.
 * @queryParam user_id required The id of the user. Example: me
 * @queryParam page required The page number. Example: 4
 * @queryParam filters.* The filters.
 * @queryParam url_encoded  Used for testing that URL parameters will be URL-encoded where needed. Example: + []&=
 * @bodyParam user_id int required The id of the user. Example: 9
 * @bodyParam room_id string The id of the room.
 * @bodyParam forever boolean Whether to ban the user forever. Example: false
 * @bodyParam another_one number Just need something here.
 * @bodyParam yet_another_param object required
 * @bodyParam even_more_param array
 * @bodyParam book.name string
 * @bodyParam book.author_id integer
 * @bodyParam book[pages_count] integer
 * @bodyParam ids.* integer
 * @bodyParam users.*.first_name string The first name of the user. Example: John
 * @bodyParam users.*.last_name string The last name of the user. Example: Doe
 */
class TestRequest extends FormRequest
{
    public function rules()
    {
        return [
            'user_id' => BodyParam::description('The id of the user.')
                ->example(9)->rules('int|required'),
            'room_id' => BodyParam::description('The id of the room.')->rules(['string']),
            'forever' => BodyParam::description('Whether to ban the user forever.')
                ->example(false)->rules('boolean'),
            'another_one' => BodyParam::description('Just need something here.')->rules('numeric'),
            'even_more_param' => BodyParam::description('')->rules('array'),
            'book.name' => BodyParam::description('')->rules('string'),
            'book.author_id' => BodyParam::description()->rules('integer'),
            'book[pages_count]' => BodyParam::description()->rules('integer'),
            'ids.*' => BodyParam::description()->rules('integer'),
            'users.*.first_name' => BodyParam::description('The first name of the user.')->example('John')->rules(['string']),
            'users.*.last_name' => BodyParam::description('The last name of the user.')->example('Doe')->rules('string'),
            'gets_ignored' => 'string',
        ];
    }
}
