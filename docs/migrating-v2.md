Scribe 2 comes with a bunch of changes focused on making the documentation process easier and the output better. Some of these changes were introduced in recent minor versions, so we'll highlight them here in case you missed them.

- "Try It Out" button gives you free interactive documentation 

## The new `description` field replaces `postman.description`
The `description` field, where you can add a description of your API. This field will be used in the following ways:
- as the `info.description` field in the Postman collection
- as the `info.description` field in the OpenAPI spec
- as the first paragraph under the "Introduction" section on the webpage, before the `intro_text`

Since we've added this field, we've removed the Postman-specific `postman.description`.

## `postman.auth` has been removed in favour of `postman.overrides`
We've removed `postman.auth`. It didn't make sense to have two ways of setting Postman-specific auth information (`postman.auth` and `postman.overrides`).

How to migrate: If you need to set Postman-specific auth now, use an `auth` key in `postman.overrides`:

```php
'postman' => [
  'overrides' => [
    'auth' => [], // your auth info
  ]
]
```


## Types are now supported for URL and query parameters
Previously, you couldn't specify types for URL and query parameters. The idea was that it didn't make sense, since they're all passed as strings in the URL anyway. But we've changed that. The thinking now is that these types can pass semantic information to your API consumers—even though they're strings in the URL, they have actual significance outside of that. You can now pass types for URL and query parameters.

How to migrate:
If you don't want to use this, no problem! All URL and query parameters will remain `string` by default. If you'd like to add types, just specify a type with @urlParam and @queryParam like you'd  do with @bodyParam (after the parameter name).

If you have custom strategies, you should update them

## New syntax for array and object parameters
The old dot notation syntax was based on Laravel's validation syntax. However, it had a few limitations in our case. It wasn't well-thought out, and was based on PHP semantics rather than JSON, which meant it didn't fit really well for documenting types. The new syntax uses some elements of the old.

How to migrate: 

Description | Old | New
-----------|------|---------
To denote an array `cars` of elements of type x | cars array, cars.* x | cars x[]
To denote an object `cars` | cars object | cars object
To denote an object `cars` with fields | cars object, cars.name string | cars object, cars.name string
To denote an array of objects `cars` with fields | cars.* object, cars.*.name string | cars object[], cars[].name string

Replace `.*.` in docblocks with '[].'
Replace `.*` in docblocks with `[]` appended to the type name 
Ensure there's parent object for object fields

## add_routes: Postman collection route renamed
When you use `laravel` type docs and have `add_routes` set to `true`, you'll have three routes added to your Laravel app: one for the webpage, one for the Postman collection and one for the OpenAPI spec. The route for the Postman collection was previously named `scribe.json`, but has now been renamed to `scribe.postman`, to bring it in line with the OpenAPI route, which is named `scribe.openapi`.

## Switch Postman base URL to use variables
Postman collection base URL now uses a variale, so you can change the base URL for all endpoints in your collection easier.

## Represent object/array fields better in docs

## New config file structure

- title
- description
- output => 
    'webpage' => type, intro_text, output_path, logo, etc
    'postman' =>
    'openapi' => 
   ]
   
   
   
   ## `auth.default` key added
   
   # API: include 'name' in parameter details
   
   ## @responseFile supports other directories