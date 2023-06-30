<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Tools\Utils;

/**
 * @group Group A
 */
class TestController extends Controller
{
    public function dummy()
    {
        return '';
    }

    /**
     * Example title.
     * This will be the long description.
     * It can also be multiple lines long.
     */
    public function withEndpointDescription()
    {
        return '';
    }

    /**
     * @group Group B
     */
    public function withGroupOverride()
    {
        return 'Group B, baby!';
    }

    /**
     * This is also in Group B. No route description. Route title before gropp.
     *
     * @group Group B
     */
    public function withGroupOverride2()
    {
        return '';
    }

    /**
     * @group Group B
     *
     * This is also in Group B. Route title after group.
     */
    public function withGroupOverride3()
    {
        return '';
    }

    /**
     * This is in Group C. Route title before group.
     *
     * @group Group C
     *
     * Group description after group.
     */
    public function withGroupOverride4()
    {
        return '';
    }

    /**
     * Endpoint with body parameters.
     *
     * @bodyParam user_id int required The id of the user. Example: 9
     * @bodyParam room_id string The id of the room.
     * @bodyParam forever boolean Whether to ban the user forever. Example: false
     * @bodyParam another_one number Just need something here.
     * @bodyParam yet_another_param object required Some object params.
     * @bodyParam yet_another_param.name string required
     * @bodyParam even_more_param number[] A list of numbers
     * @bodyParam book object
     * @bodyParam book.name string
     * @bodyParam book.author_id integer
     * @bodyParam book.pages_count integer
     * @bodyParam ids int[]
     * @bodyParam users object[]
     * @bodyParam users[].first_name string required The first name of the user. Example: John
     * @bodyParam users[].last_name string required The last name of the user. Example: Doe
     */
    public function withBodyParameters()
    {
        return '';
    }

    /**
     * Endpoint with body form data parameters.
     *
     * @bodyParam name string required Name of image. Example: cat.jpg
     * @bodyParam image file required The image. Example: config/scribe.php
     */
    public function withFormDataParams()
    {
        request()->validate(['image' => 'file|required']);
        return [
            'filename' => request()->file('image')->getFilename(),
            'filepath' => request()->file('image')->getPath(),
            'name' => request('name'),
        ];
    }

    /**
     * Endpoint with body parameters as array.
     *
     * @bodyParam [] object[] Details.
     * @bodyParam [].first_name string required The first name of the user. Example: John
     * @bodyParam [].last_name string required The last name of the user. Example: Doe
     * @bodyParam [].contacts object[] required Contact info
     * @bodyParam [].contacts[].first_name string required The first name of the contact. Example: Janelle
     * @bodyParam [].contacts[].last_name string required The last name of the contact. Example: Monáe
     * @bodyParam [].roles string[] required The name of the role. Example: ["Admin"]
     */
    public function withBodyParametersAsArray()
    {
        return '';
    }

    public function withFormRequestParameter(string $test, TestRequest $request)
    {
        return '';
    }

    public function withFormRequestParameterQueryParams(string $test, TestRequestQueryParams $request)
    {
        return '';
    }

    public function withFormRequestParameterQueryParamsComment(string $test, TestRequestQueryParamsComment $request)
    {
        return '';
    }

    /**
     * @bodyParam direct_one string Is found directly on the method.
     */
    public function withNonCommentedFormRequestParameter(TestNonCommentedRequest $request)
    {
        return '';
    }

    /**
     * @queryParam location_id required The id of the location.
     * @queryParam user_id required The id of the user. Example: me
     * @queryParam page required The page number. Example: 4
     * @queryParam filters The filters.
     * @queryParam url_encoded  Used for testing that URL parameters will be URL-encoded where needed. Example: + []&=
     */
    public function withQueryParameters()
    {
        return '';
    }

    /**
     * @bodyParam included string required Exists in examples. Example: 'Here'
     * @bodyParam  excluded_body_param int Does not exist in examples. No-example
     * @queryParam excluded_query_param Does not exist in examples. No-example
     */
    public function withExcludedExamples()
    {
        return '';
    }

    /**
     * @authenticated
     * @responseField user_id string The ID of the newly created user
     * @responseField creator_id string The ID of the creator
     */
    public function withAuthenticatedTag()
    {
        return '';
    }

    /**
     * @responseField user_id string The ID of the newly created user
     * @responseField creator_id string The ID of the creator
     */
    public function withResponseFieldTag()
    {
        return '';
    }

    /**
     * @apiResource \Knuckles\Scribe\Tests\Fixtures\TestUserApiResource
     * @apiResourceModel \Knuckles\Scribe\Tests\Fixtures\TestUser
     */
    public function withEloquentApiResource()
    {
        return new TestUserApiResource(Utils::getModelFactory(TestUser::class)->make(['id' => 0]));
    }

    /**
     * @apiResource \Knuckles\Scribe\Tests\Fixtures\TestEmptyApiResource
     */
    public function withEmptyApiResource()
    {
        return new TestEmptyApiResource();
    }

    /**
     * @group Other😎
     *
     * @apiResourceCollection Knuckles\Scribe\Tests\Fixtures\TestUserApiResource
     * @apiResourceModel Knuckles\Scribe\Tests\Fixtures\TestUser
     */
    public function withEloquentApiResourceCollection()
    {
        return TestUserApiResource::collection(
            collect([Utils::getModelFactory(TestUser::class)->make(['id' => 0])])
        );
    }

    /**
     * @group Other😎
     *
     * @apiResourceCollection Knuckles\Scribe\Tests\Fixtures\TestUserApiResourceCollection
     * @apiResourceModel Knuckles\Scribe\Tests\Fixtures\TestUser
     */
    public function withEloquentApiResourceCollectionClass()
    {
        return new TestUserApiResourceCollection(
            collect([Utils::getModelFactory(TestUser::class)->make(['id' => 0])])
        );
    }

    public function checkCustomHeaders(Request $request)
    {
        return $request->headers->all();
    }

    public function shouldFetchRouteResponse()
    {
        $fruit = new \stdClass();
        $fruit->id = 4;
        $fruit->name = ' banana  ';
        $fruit->color = 'RED';
        $fruit->weight = 1;
        $fruit->delicious = true;

        return [
            'id' => (int) $fruit->id,
            'name' => trim($fruit->name),
            'color' => strtolower($fruit->color),
            'weight' => $fruit->weight . ' kg',
            'delicious' => $fruit->delicious,
            'responseCall' => true,
        ];
    }

    public function echoesConfig()
    {
        return [
            'app.env' => config('app.env'),
        ];
    }

    /**
     * @group Other😎
     *
     * @urlParam param required Example: 4
     * @urlParam param2 required
     * @urlParam param4 No-example.
     *
     * @queryParam something
     */
    public function echoesUrlParameters($param, $param2, $param3 = null, $param4 = null)
    {
        return compact('param', 'param2', 'param3', 'param4');
    }

    /**
     * @authenticated
     * @urlparam id Example: 3
     */
    public function echoesRequestValues($id)
    {
        return [
            '{id}' => $id,
            'header' => request()->header('header'),
            'auth' => request()->header('Authorization'),
            'queryParam' => request()->query('queryParam'),
            'bodyParam' => request()->get('bodyParam'),
        ];
    }

    /**
     * @response {
     *   "result": "Лорем ипсум долор сит амет"
     * }
     */
    public function withUtf8ResponseTag()
    {
        return ['result' => 'Лорем ипсум долор сит амет'];
    }

    /**
     * @hideFromAPIDocumentation
     */
    public function skip()
    {
    }

    /**
     * @response {
     *   "id": 4,
     *   "name": "banana",
     *   "color": "red",
     *   "weight": "1 kg",
     *   "delicious": true,
     *   "responseTag": true
     * }
     */
    public function withResponseTag()
    {
        return '';
    }

    /**
     * @response 422 {
     *   "message": "Validation error"
     * }
     */
    public function withResponseTagAndStatusCode()
    {
        return '';
    }

    /**
     * @response {
     *   "id": 4,
     *   "name": "banana",
     *   "color": "red",
     *   "weight": "1 kg",
     *   "delicious": true,
     *   "multipleResponseTagsAndStatusCodes": true
     * }
     * @response 401 {
     *   "message": "Unauthorized"
     * }
     */
    public function withMultipleResponseTagsAndStatusCode()
    {
        return '';
    }

    /**
     * @transformer \Knuckles\Scribe\Tests\Fixtures\TestTransformer
     */
    public function transformerTag()
    {
        return '';
    }

    /**
     * @transformer 201 \Knuckles\Scribe\Tests\Fixtures\TestTransformer
     */
    public function transformerTagWithStatusCode()
    {
        return '';
    }

    /**
     * @transformer \Knuckles\Scribe\Tests\Fixtures\TestTransformer
     * @transformermodel \Knuckles\Scribe\Tests\Fixtures\TestModel
     */
    public function transformerTagWithModel()
    {
        return '';
    }

    /**
     * @transformercollection \Knuckles\Scribe\Tests\Fixtures\TestTransformer
     */
    public function transformerCollectionTag()
    {
        return '';
    }

    /**
     * @transformercollection \Knuckles\Scribe\Tests\Fixtures\TestTransformer
     * @transformermodel \Knuckles\Scribe\Tests\Fixtures\TestModel
     */
    public function transformerCollectionTagWithModel()
    {
        return '';
    }

    /**
     * @responseFile response_test.json
     */
    public function responseFileTag()
    {
        return '';
    }

    /**
     * @responseFile response_test.json
     * @responseFile 401 response_error_test.json
     */
    public function withResponseFileTagAndStatusCode()
    {
        return '';
    }

    /**
     * @responseFile response_test.json {"message" : "Serendipity"}
     */
    public function responseFileTagAndCustomJson()
    {
        return '';
    }

    /**
     * @responseFile i-do-not-exist.json
     */
    public function withNonExistentResponseFile()
    {
        return '';
    }

    public function withInlineRequestValidate(Request $request)
    {
        // Some stuff
        $validated = $request->validate([
            // The id of the user. Example: 9
            'user_id' => 'int|required',
            // The id of the room.
            'room_id' => ['string', 'in:3,5,6'],
            // Whether to ban the user forever. Example: false
            'forever' => 'boolean',
            // Just need something here. No-example
            'another_one' => 'numeric',
            'even_more_param' => 'array',
            'book.name' => 'string',
            'book.author_id' => 'integer',
            'book.pages_count' => 'integer',
            'ids.*' => 'integer',
            // The first name of the user. Example: John
            'users.*.first_name' => ['string'],
            // The last name of the user. Example: Doe
            'users.*.last_name' => 'string',
        ]);

        // Do stuff
    }

    public function withInlineRequestValidateNoAssignment(Request $request)
    {
        $request->validate([
            // The id of the user. Example: 9
            'user_id' => 'int|required',
            // The id of the room.
            'room_id' => ['string', 'in:3,5,6'],
            // Whether to ban the user forever. Example: false
            'forever' => 'boolean',
            // Just need something here. No-example
            'another_one' => 'numeric',
            'even_more_param' => 'array',
            'book.name' => 'string',
            'book.author_id' => 'integer',
            'book.pages_count' => 'integer',
            'ids.*' => 'integer',
            // The first name of the user. Example: John
            'users.*.first_name' => ['string'],
            // The last name of the user. Example: Doe
            'users.*.last_name' => 'string',
        ]);

        // Do stuff
    }

    public function withInlineRequestValidateQueryParams(Request $request)
    {
        // Query parameters
        $validated = $request->validate([
            // The id of the user. Example: 9
            'user_id' => 'int|required',
            // The id of the room.
            'room_id' => ['string', 'in:3,5,6'],
            // Whether to ban the user forever. Example: false
            'forever' => 'boolean',
            // Just need something here. No-example
            'another_one' => 'numeric',
            'even_more_param' => 'array',
            'book.name' => 'string',
            'book.author_id' => 'integer',
            'book.pages_count' => 'integer',
            'ids.*' => 'integer',
            // The first name of the user. Example: John
            'users.*.first_name' => ['string'],
            // The last name of the user. Example: Doe
            'users.*.last_name' => 'string',
        ]);

        // Do stuff
    }

    public function withInlineValidatorMake(Request $request)
    {
        // Some stuff
        $validator = Validator::make($request, [
            // The id of the user. Example: 9
            'user_id' => 'int|required',
            // The id of the room.
            'room_id' => ['string', 'in:3,5,6'],
            // Whether to ban the user forever. Example: false
            'forever' => 'boolean',
            // Just need something here. No-example
            'another_one' => 'numeric',
            'even_more_param' => 'array',
            'book.name' => 'string',
            'book.author_id' => 'integer',
            'book.pages_count' => 'integer',
            'ids.*' => 'integer',
            // The first name of the user. Example: John
            'users.*.first_name' => ['string'],
            // The last name of the user. Example: Doe
            'users.*.last_name' => 'string',
        ]);

        // Do stuff
        if ($validator->fails()) {

        }
    }

    public function withInlineRequestValidateWithBag(Request $request)
    {
        $request->validateWithBag('stuff', [
            // The id of the user. Example: 9
            'user_id' => 'int|required',
            // The id of the room.
            'room_id' => ['string', 'in:3,5,6'],
            // Whether to ban the user forever. Example: false
            'forever' => 'boolean',
            // Just need something here. No-example
            'another_one' => 'numeric',
            'even_more_param' => 'array',
            'book.name' => 'string',
            'book.author_id' => 'integer',
            'book.pages_count' => 'integer',
            'ids.*' => 'integer',
            // The first name of the user. Example: John
            'users.*.first_name' => ['string'],
            // The last name of the user. Example: Doe
            'users.*.last_name' => 'string',
        ]);

        // Do stuff
    }

    public function withInlineThisValidate(Request $request)
    {
        $this->validate($request, [
            // The id of the user. Example: 9
            'user_id' => 'int|required',
            // The id of the room.
            'room_id' => ['string', 'in:3,5,6'],
            // Whether to ban the user forever. Example: false
            'forever' => 'boolean',
            // Just need something here. No-example
            'another_one' => 'numeric',
            'even_more_param' => 'array',
            'book.name' => 'string',
            'book.author_id' => 'integer',
            'book.pages_count' => 'integer',
            'ids.*' => 'integer',
            // The first name of the user. Example: John
            'users.*.first_name' => ['string'],
            // The last name of the user. Example: Doe
            'users.*.last_name' => 'string',
        ]);

        // Do stuff
    }

    public function withInjectedModel(TestUser $user)
    {
        return null;
    }
    
    public function withInjectedModelFullParamName(TestPost $testPost)
    {
        return null;
    }

    public function withEnumRule(Request $request)
    {
        $request->validate([
            'enum_class' => ['required', new Rules\Enum(\Knuckles\Scribe\Tests\Fixtures\TestStringBackedEnum::class), 'nullable'],
            'enum_string' => ['required', Rule::enum('\Knuckles\Scribe\Tests\Fixtures\TestIntegerBackedEnum'), 'nullable'],
            // Not full path class call won't work
            'enum_inexistent' => ['required', new Rules\Enum(TestStringBackedEnum::class)],
        ]);
    }

    /**
     * Can only run on PHP 8.1
    public function withInjectedEnumAndModel(Category $category, TestUser $user)
    {
        return null;
    }
     */
}

/**
enum Category: string
{
    case Fruits = 'fruits';
    case People = 'people';
}
*/
