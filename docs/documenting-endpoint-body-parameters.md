# Documenting parameters for an endpoint
[IN PROGRESS]

## Specifying request parameters
To specify a list of valid parameters your API route accepts, use the `@urlParam`, `@bodyParam` and `@queryParam` annotations.
- The `@urlParam` annotation is used for describing parameters in your URl. For instance, in a Laravel route with this URL: "/post/{id}/{lang?}", you would use this annotation to describe the `id` and `lang` parameters. It takes the name of the parameter, an optional "required" label, and then its description.
- The `@queryParam` annotation takes the name of the parameter, an optional "required" label, and then its description.
- The `@bodyParam` annotation takes the name of the parameter, its type, an optional "required" label, and then its description. 

Examples:

```php
/**
 * @urlParam id required The ID of the post.
 * @urlParam lang The language.
 * @bodyParam user_id int required The id of the user. Example: 9
 * @bodyParam room_id string The id of the room.
 * @bodyParam forever boolean Whether to ban the user forever. Example: false
 * @bodyParam another_one number Just need something here.
 * @bodyParam yet_another_param object required Some object params.
 * @bodyParam yet_another_param.name string required Subkey in the object param.
 * @bodyParam even_more_param array Some array params.
 * @bodyParam even_more_param.* float Subkey in the array param.
 * @bodyParam book.name string
 * @bodyParam book.author_id integer
 * @bodyParam book[pages_count] integer
 * @bodyParam ids.* integer
 * @bodyParam users.*.first_name string The first name of the user. Example: John
 * @bodyParam users.*.last_name string The last name of the user. Example: Doe
 */
public function createPost()
{
    // ...
}

/**
 * @queryParam sort Field to sort by
 * @queryParam page The page number to return
 * @queryParam fields required The fields to include
 */
public function listPosts()
{
    // ...
}
```

They will be included in the generated documentation text and example requests.

**Result:**

![](./../body_params_1.png)

![](./../body_params_2.png)

### Example parameters
For each parameter in your request, this package will generate a random value to be used in the example requests. If you'd like to specify an example value, you can do so by adding `Example: your-example` to the end of your description. For instance:

```php
    /**
     * @queryParam location_id required The id of the location.
     * @queryParam user_id required The id of the user. Example: me
     * @queryParam page required The page number. Example: 4
     * @bodyParam user_id int required The id of the user. Example: 9
     * @bodyParam room_id string The id of the room.
     * @bodyParam forever boolean Whether to ban the user forever. Example: false
     */
```

You can also exclude a particular parameter from the generated examples (for all languages) by annotating it with `No-example`. For instance:
```php
    /**
    * @queryParam location_id required The id of the location. Example: 1
    * @queryParam user_id required The id of the user. No-example
    * @queryParam page required The page number. Example: 4
    */
```
Outputs: 
```bash
curl -X GET -G "https://example.com/api?location_id=1&page=4"
```

Note: You can also add the `@queryParam` and `@bodyParam` annotations to a `\Illuminate\Foundation\Http\FormRequest` subclass instead, if you are using one in your controller method

```php
/**
 * @queryParam user_id required The id of the user. Example: me
 * @bodyParam title string required The title of the post.
 * @bodyParam body string required The content of the post.
 * @bodyParam type string The type of post to create. Defaults to 'textophonious'.
 * @bodyParam author_id int the ID of the author. Example: 2
 * @bodyParam thumbnail image This is required if the post type is 'imagelicious'.
 */
class MyRequest extends \Illuminate\Foundation\Http\FormRequest
{

}

// in your controller...
public function createPost(MyRequest $request)
{
    // ...
}
```
