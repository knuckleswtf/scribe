# Configuration
Here's a rundown of what's available in the `config/scribe.php` file. 

```eval_rst
.. Tip:: If you aren't sure what an option does, it's best to leave it set to the default.
```

## Output settings
### `type`
This is the type of documentation output to generate.
- `static` will generate a static HTMl page in the `public/docs` folder, so anyone can visit your documentation page by going to {yourapp.domain}/docs.
- `laravel` will generate the documentation as a Blade view within the `resources/views/scribe` folder, so you can add routing and authentication.

```eval_rst
.. Note:: In both instances, the source markdown file will be generated in `resources/docs`.
```

### `static`
Settings for the `static` type output.

- `output_path`: Output folder. The HTML documentation, assets and Postman collection will be generated to this folder. Source Markdown will still be in resources/docs. Default: `public/docs`.

### `laravel`
Settings for the `laravel` type output.

- `add_routes`: Set this to `true` if you want the documentation endpoint to be automatically set up for you. Of course, you can use your own routing instead, by setting this to `false`.

- `docs_url`: The path for the documentation endpoint (if `add_routes` is true). Your Postman collection (if you have that enabled) will be at this path + '.json' (eg `/docs.json`). Default: `/docs`.

- `middleware`: List of middleware to be attached to the documentation endpoint (if `add_routes` is true).

### `base_url`
The base URL to be used in examples. By default, this will be the value of `config('app.url')`.

### `intro_text`
The text to place in the "Introduction" section. Markdown and HTML are supported.

### `title`
The HTML `<title>` for the generated documentation, and the name of the generated Postman collection. If this is `null`, Scribe will infer it from `config('app.name')`.

### `description`
A description for your API. This will be placed in the "Introduction" section, before the `intro_text`. It will also be used as the `info.description` field in the generated Postman collection and OpenAPI spec.

### `interactive`
Set this to `true` if you'd like Scribe to add a "Try It Out" button to your endpoints so users can test them from their browser. Default: `true`.

```eval_rst
..Important:: For "Try It Out" to work, you'll need to make sure CORS is enabled on your endpoints. An easy package for this is `fruitcake/laravel-cors <https://github.com/fruitcake/laravel-cors>`_.
```

### `logo`
Path to an image file to use as your logo in the generated docs. This will be used as the value of the src attribute for the `<img>` tag, so make sure it points to a public URL or path accessible from your web server. For best results, the image width should be 230px. Set this to `false` if you're not using a logo. Default: `false`.

```eval_rst
.. Important:: If you're using a relative path, remember to make it relative to your docs output location (:code:`static` type) or app URL (:code:`laravel` type). For example, if your logo is in public/img:

   - for :code:`static` type (output folder is public/docs), use '../img/logo.png'
   - for :code:`laravel` type, use 'img/logo.png'
```

### `default_group`
When [documenting your api](documenting/index.html), you use `@group` annotations to group API endpoints. Endpoints which do not have a group annotation will be grouped under the `default_group`. Defaults to `"Endpoints"`.

### `example_languages`
For each endpoint, an example request is shown in each of the languages specified in this array. Currently only `bash`, `javascript`, `php` and `python` are supported. You can add your own language, but you must also define the corresponding Blade view (see [Adding more example languages](customisation#adding-more-example-languages)). Default: `["bash", "javascript"]` 
 
### `postman`
Along with the HTML docs, Scribe can automatically generate a Postman collection for your routes. This section is where you can configure or disable that.

For `static` output, the collection will be created in `public/docs/collection.json`. For `laravel` output, the collection will be generated to `storage/app/scribe/collection.json`. Setting `laravel.add_routes` to `true` will add a `/docs.json` endpoint to fetch it.

- `enabled`: Whether or not to generate a Postman API collection. Default: `true`

- `overrides`: List of fields to apply to the generated collection. Dot notation is supported. For instance, if you'd like to override the version (in the `info` object, you can set `overrides` to `['info.version' => '2.0.0']`.

### `openapi`
Scribe can also generate an OpenAPI (Swagger) spec for your routes (disabled by default). This section is where you can configure or enable that.

```eval_rst
.. Important:: The OpenAPI spec is an opinionated spec that doesn't cover all features of APIs in the wild. Scribe does its best, but there's no guarantee that the spec generated will exactly match your API structure.
```

For `static` output, the spec will be created in `public/docs/openapi.yaml`. For `laravel` output, the spec will be generated to `storage/app/scribe/openapi.yaml`. Setting `laravel.add_routes` to `true` will add a `/docs.openapi` endpoint to fetch it.

- `enabled`: Whether or not to generate an OpenAPI spec. Default: `false`

- `overrides`: List of fields to apply to the generated spec. Dot notation is supported. For instance, if you'd like to override the version (in the `info` object, you can set `overrides` to `['info.version' => '2.0.0']`.

## Extraction settings
### `router`
The router to use when processing your routes. Can be `laravel` or `dingo`. Defaults to `laravel`.

### `auth`
Authentication information about your API. This information will be used:
- to derive the text in the "Authentication" section in the generated docs
- to generate auth info in the Postman collection and OpenAPI spec
- to add the auth headers/query parameters/body parameters to the docs and example requests
- to set the auth headers/query parameters/body parameters for response calls

Here are the available settings:
- `enabled`: Set this to `true` if any endpoints in your API use authentication. Default: `false`.

- `default`: Specify the default behaviour of your API. If you set this to `true`, your endpoints will be authenticated by default, and you can opt out individually with the `@unauthenticated` tag. If you set this to `false`, your endpoints will NOT be authenticated by default, and you can turn on auth individually with the `@authenticated` tag. Don't forget to set `auth.enabled` to `true`!  Default: `false`.

- `in`: Where is the auth value meant to be sent in a request? Options:
  - `query` (for a query parameter)
  - `body` (for a body parameter)
  - `basic` (for HTTP Basic auth via an Authorization header)
  - `bearer`(for HTTP Bearer auth via an Authorization header)
  - `header` (for auth via a custom header)

- `name`: The name of the parameter (eg `token`, `key`, `apiKey`) or header (eg `Authorization`, `Api-Key`). When `in` is set to `bearer` or `basic`, this value will be ignored, and the header used will be `Authorization`.

- `use_value`: The value of the parameter to be used by Scribe to authenticate response calls. This will **not** be included in the generated documentation. If this value is null, Scribe will use a random value.

- `placeholder`: Placeholder your users will see for the auth parameter in the example requests. If this is empty, Scribe will generate a realistic-looking auth token instead. Defaults to: "{YOUR_AUTH_KEY}".

- `extra_info`: Any extra authentication-related info for your users. For instance, you can describe how to find or generate their auth credentials. Markdown and HTML are supported. This will be included in the `Authentication` section.

### `strategies`
A nested array of strategies Scribe will use to extract information about your routes at each stage. If you write or install a custom strategy, add it here under the appropriate stage. By default, this is set to:

```php
    'strategies' => [
        'metadata' => [
            \Knuckles\Scribe\Extracting\Strategies\Metadata\GetFromDocBlocks::class,
        ],
        'urlParameters' => [
            \Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromLaravelAPI::class,
            \Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromUrlParamTag::class,
        ],
        'queryParameters' => [
            \Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromQueryParamTag::class,
        ],
        'headers' => [
            \Knuckles\Scribe\Extracting\Strategies\Headers\GetFromRouteRules::class,
            \Knuckles\Scribe\Extracting\Strategies\Headers\GetFromHeaderTag::class,
        ],
        'bodyParameters' => [
            \Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromFormRequest::class,
            \Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromBodyParamTag::class,
        ],
        'responses' => [
            \Knuckles\Scribe\Extracting\Strategies\Responses\UseTransformerTags::class,
            \Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseTag::class,
            \Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseFileTag::class,
            \Knuckles\Scribe\Extracting\Strategies\Responses\UseApiResourceTags::class,
            \Knuckles\Scribe\Extracting\Strategies\Responses\ResponseCalls::class,
        ],
        'responseFields' => [
            \Knuckles\Scribe\Extracting\Strategies\ResponseFields\GetFromResponseFieldTag::class,
        ],
    ],
```

### `routes`
The `routes` section is an array of items describing what routes in your application that should be included in the generated documentation.

Each item in the `routes` array is a _route group_, an array containing rules defining what routes belong in that group, and what settings to apply to them.

- `match`: Here you define the rules that will be used to determine what routes in your application fall into this group. There are three kinds of rules defined here (keys in the `match` array):

- `domains`: This key takes an array of domain names as its value. Only routes which are defined on the domains specified here will be matched as part of this group. Defaults to `[*]` (routes on all domains).
 
- `prefixes`: The prefixes key is similar to the `domains` key, but is based on URL path prefixes (ie. what the part starts with, after the domain name). Defaults to `[*]` (all routes, regardless of path).
 
```eval_rst
.. Important:: The :code:`domains` and :code:`prefixes` keys are required for all route groups.
```

- `versions`: This only applies when `router` is `dingo`. When using Dingo router, all routes must be specified inside versions. This means that you must specify the versions to be matched along with the domains and prefixes when describing a route group.

```eval_rst
.. Important:: Wildcards in :code:`versions` are not supported; you must list out all the versions you want to match.
 ```
 - `include`: A list of patterns (route names or paths) which should be included in this group, *even if they do not match the rules in the `match` section*.

- `exclude`: A list of patterns (route names or paths) which should be excluded from this group, *even if they match the rules in the `match` section*.

For instance, supposing our routes are set up like this:

```php
Route::group(['domain' => 'v2-api.acme.co'], function () {
  Route::resource('/apps', 'AppController@listApps');
  Route::get('/users', 'UserController@listUsers')
    ->name('users.list');
  Route::get('/users/{id}', 'UserController@getUser')
    ->name('users.get');
  Route::get('/status', 'StatusController@getStatus')
    ->name('status');
});

Route::group(['domain' => 'api.acme.co'], function () {
  Route::get('/getUsers', 'v1\UserController@getUsers')
    ->name('v1.getUsers');
  Route::get('/metrics', 'PublicController@getStats')
    ->name('public.metrics');
});
```

If we only want to match endpoints on the `v2-api.acme.co` domain and we want to exclude the `/status` route but include the metrics route from `api.acme.co`, we could use this configuration:

```php
    'match' => [
      'domains' => ['api.acme.co'],
      'prefixes' => ['*'],
    ],
    'include' => ['public.metrics'],
    'exclude' => ['status'],
```

```eval_rst
.. Tip:: You can use :code:`*` as a wildcard in :code:`domains, :code:`prefixes`, :code:`include` and :code:`exclude`. For instance, :code:`'exclude' => ['users/*']` will exclude all routes with URLs starting with 'users/'.
```

- `apply`: The `apply` section of the route group is where you specify any additional settings to be applied to those routes when generating documentation. There are a number of settings you can tweak here:

  - `headers`: Any headers you specify here will be added to the headers shown in the example requests in your documentation. They will also be included in response calls. Headers are specified as key => value strings.

  - `response_calls`: These are the settings that will be applied when making ["response calls"](documenting/documenting-endpoint-responses.html#generating-responses-automatically-via-response-calls). 

```eval_rst
.. Tip:: By splitting your routes into groups, you can apply different settings to different routes.
```

### `faker_seed`
When generating example requests, this package uses the fzanninoto/faker package to generate random values. If you would like the package to generate the same example values for parameters on each run, set this to any number (eg. 1234).

```eval_rst
.. Tip:: Alternatively, you can set example values for parameters when `documenting them <documenting.html>`_.
```

### `fractal`
This section only applies if you're using [transformers](https://fractal.thephpleague.com/transformers/) for your API (via the league/fractal package), and documenting responses with `@transformer` and `@transformerCollection`. Here, you configure how responses are transformed.

- `serializer`: If you are using a custom serializer with league/fractal,  you can specify it here. league/fractal comes with the following serializers:
  - `\League\Fractal\Serializer\ArraySerializer::class`
  - `\League\Fractal\Serializer\DataArraySerializer::class`
  - `\League\Fractal\Serializer\JsonApiSerializer::class`

  Leave this as `null` to use no serializer or return a simple JSON.


### `routeMatcher`
The route matcher class provides the algorithm that determines what routes should be documented. The default matcher used is the included `\Knuckles\Scribe\Matching\RouteMatcher::class`, and you can provide your own custom implementation if you wish to programmatically change the algorithm. The provided matcher should be an instance of `\Knuckles\Scribe\Matching\RouteMatcherInterface`.

### `database_connections_to_transact`
Scribe tries to run response calls and example model creation (API Resource and Transformer strategies) in a database transaction, and then roll it back so no changes are persisted. This item is where you specify which connections Scribe should run in transactions for.

By default, this is set to your default database connection (`[config('database.default)]`), so if you only use one database connections, you should be fine. If you use multiple connections, you can add the rest to the array:

```php
'database_connections_to_transact' => [
    config('database.default'),
    'pgsql',
],
```

### `continue_without_database_transactions` [deprecated]

```eval_rst
.. Warning:: This config item is deprecated and going away in v3. Use :code:`database_connections_to_transact` instead.
```

By default, Scribe runs response calls and example model creation in a database transaction, and then rolls them back so no changes are persisted. If one of your database drivers does not support database transactions, Scribe will log an error and exit. If you would like Scribe to proceed (and persist the data), add the database driver class name to this array. For example:

```php
'continue_without_database_transactions' => [
    Jenssegers\Mongodb\Connection::class,
],
```
