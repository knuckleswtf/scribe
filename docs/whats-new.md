## Documenting responses
You can now give readers more information about the fields they can expect in your responses. This functionality is provided by default by the `UseResponseFieldTags` strategy. You use it by adding a `@responseField` annotation to your controller method.

```
@responseField id integer The id of the newly created user
```

Note that this also works the same way for array responses. So if your response is an array of objects, you should only mention the keys of the objects inside the array. So the above annotation will work fine for both this response:

```
{
  "id": 3
}
```

and this:

```
[
  { "id": 3 }
]
```

You can also omit the type of the field. Scribe will try to figure it out from the 2xx responses for that endpoint. So this gives the same result:

```
@responseField id integer The id of the newly created user
```

Result:

![](./images/response-fields.png)


## Automatic routing for `laravel` docs
The `autoload` key in `laravel` config is now `add_routes`, and is `true` by default. This means you don't have to do any extra steps to serve your docs through you Laravel app.

## Authentication
Scribe can now add authentication information to your docs! To get this, you'll need to use the `auth` section in the config file.

The info you provide will be used in generating a description of the authentication text, as well as adding the needed parameters in the example requests, and in response calls. See that section of the docs for details.

## More customization options
You can now customise the introductory text by setting the `intro_text` key in your scribe.php. 

## Reworked Strategy API
- `stage` property.
