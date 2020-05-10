# Documenting body and file parameters for an endpoint

## Specifying body parameters
To describe query parameters for your endpoint, use the `@bodyParam` annotation on the method handling it.

The `@bodyParam` annotation takes the name of the parameter, its type, an optional "required" label, and then its description. Valid types:
- `int` / `integer`
- `string`
- `number` / `float`
- `boolean`
- `array`
- `object`
- `file` (see [Documenting File Uploads](#documenting-file-uploads) below)

By default, Scribe will generate a random value for each parameter, to be used in the example requests and response calls. If you'd like to use a specific example value, you can do so by adding `Example: your-example-here` to the end of your description.

You can also exclude a particular parameter from the generated examples by ending with `No-example` instead. This will also prevent the parameter from being sent along in response calls. The parameter will still be included in the text of the documentation.

Here's an example:

```php
/**
 * @bodyParam user_id int required The id of the user. Example: 9
 * @bodyParam room_id string The id of the room.
 * @bodyParam forever boolean Whether to ban the user forever. Example: false
 * @bodyParam another_one number This won't be added to the examples. No-example
 */
public function createPost()
{
    // ...
}
```

The body parameters will be included in the generated documentation text and example requests:

![](images/endpoint-bodyparams-1.png)


You can also add the `@queryParam` and `@bodyParam` annotations to a `\Illuminate\Foundation\Http\FormRequest` subclass instead, if you are using one in your controller method

```php
/**
 * @bodyParam title string The title of the post.
 * @bodyParam body string required The content of the post.
 */
class CreatePostRequest extends \Illuminate\Foundation\Http\FormRequest
{

}

// in your controller...
public function createPost(CreatePostRequest $request)
{
    // ...
}
```

## Handling array and object parameters
Sometimes you have body parameters that are arrays ir ibjects. To handle them in `@bodyparam`, Scribe follows Laravel's convention:
- For arrays: use `<name>.*`
- For objects: use `<name>.<key>`.

This means that, for an array of objects, you'd use `<name>.*.<key>`.

You can also add a "parent" description if you like, by using `@bodyParam` with the type as "object" or "array".

```php
/**
 * @bodyParam user object required The user details
 * @bodyParam user.name string required The user's name
 * @bodyParam user.age string required The user's age
 * @bodyParam friend_ids array List of the user's friends.
 * @bodyParam friend_ids.* int User's friends.
 * @bodyParam cars.*.year string The year the car was made. Example: 1997
 * @bodyParam cars.*.make string The make of the car. Example: Toyota
 */
```

![](images/endpoint-bodyparams-2.png)


## Documenting file uploads
You can document file inputs by using `@bodyParam` with a type `file`. You can add a description and example as usual. 

For files, your example should be the absolute path to a file that exists on your machine. If you don't specify an example, Scribe will generate a fake file for example requests and response calls.

```php
/**
 * @bodyParam caption string The image caption
 * @bodyParam image required file The image.
 */
```

![](images/endpoint-bodyparams-3.png) 

> Note: Adding a file parameter will automatically set the 'Content-Type' header in example requests and response calls to `multipart/form-data`.
