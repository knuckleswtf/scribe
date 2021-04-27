# Documenting query and URL parameters for an endpoint

## Specifying query parameters
To describe query parameters for your endpoint, use the `@queryParam` annotation on the method handling it.

The `@queryParam` annotation takes the name of the parameter, an optional type, an optional "required" label, and a description.

If you don't specify a type, Scribe will assume it's `string`. See [the documentation on body parameters](./documenting-endpoint-body-parameters.html) for a list of valid types.

Here's an example:

```php
/**
  * @queryParam sort string Field to sort by. Defaults to 'id'.
  * @queryParam fields required Comma-separated list of fields to include in the response. Example: title,published_at,is_public
  * @queryParam filters[published_at] Filter by date published.
  * @queryParam filters[is_public] integer Filter by whether a post is public or not. Example: 1
 */
public function listPosts()
{
    // ...
}
```

The query parameters will be included in the generated documentation text and example requests:

![](../images/endpoint-queryparams-1.png)

![](../images/endpoint-queryparams-2.png)


If you need to handle array/object parameters, you can use the same conventions as [for body parameters](./documenting-endpoint-body-parameters.html#handling-array-and-object-parameters):

```php
/**
  * @queryParam sort string Field to sort by. Defaults to 'id'.
  * @queryParam fields string[] required Comma-separated list of fields to include in the response. Example: title,published_at,is_public
  * @queryParam filters object Fields to filter by
  * @queryParam filters.published_at Filter by date published.
  * @queryParam filters.is_public integer Filter by whether a post is public or not. Example: 1
 */
```

![](../images/endpoint-queryparams-4.png)

```eval_rst
.. Tip:: You should avoid using query parameters that are arrays or objects, because [there isn't a standardised format for handling them](https://stackoverflow.com/a/9547490/7370522).
```

If you're using a FormRequest in your controller, you can also add the `@queryParam` annotation there instead, and Scribe will fetch it.

```php
/**
 * @queryParam user_id required The id of the user.
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

## Specifying example values
By default, Scribe will generate a random value for each parameter, to be used in the example requests and response calls. If you'd like to use a specific example value, you can do so by adding `Example: your-example-here` to the end of your description.

```eval_rst
.. Tip:: You can exclude a particular parameter from the generated examples by ending with `No-example` instead. The parameter will still be included in the text of the documentation, but it won't be included in response calls or shown in the example requests.
```

For instance:

```php
    /**
     * @queryParam sort Field to sort by. Defaults to 'id'. Example: published_at
     * @queryParam fields required Comma-separated fields to include in the response. Example: title,published_at,id
     * @queryParam filters[published_at] Filter by date published. No-example
     * @queryParam filters[title] Filter by title. No-example
    */
```

![](../images/endpoint-queryparams-4.png)

## Describing URL parameters
To describe parameters in the URL, use the `@urlParam` annotation. For instance, if you defined your Laravel route like this:

```php
Route::get("/post/{id}/{lang?}");
```

you can use this annotation to describe the `id` and `lang` parameters as shown below. The annotation takes the name of the parameter, an optional type, an optional "required" label, and then its description. Like with `@queryParams`, a random value will be generated, but you can specify the value to be used in examples and response calls using the `Example: ` syntax.

If you don't specify a type, Scribe will assume it's `string`. You can override this value by changing the `default_parameter_type` within your configuration file. The Valid types are `string`, `integer`, and `number`.

```php
/**
 * @urlParam id integer required The ID of the post.
 * @urlParam lang The language. Example: en
 */
public function getPost()
{
    // ...
}
```

![](../images/endpoint-urlparams-1.png)

```eval_rst
.. Note:: If you want Scribe to omit an optional parameter (`lang` in our example) in requests and response calls, specify :code:`No-example` for the parameter.
```

```php
/**
 * @urlParam id required The ID of the post.
 * @urlParam lang The language. No-example
 */
public function getPost()
{
    // ...
}
```

![](../images/endpoint-urlparams-2.png)
