# Documenting headers for endpoints

```eval_rst
.. attention:: These docs are for Scribe v2, which is no longer maintained. See `scribe.knuckles.wtf/laravel <http://scribe.knuckles.wtf/laravel>`_ for Scribe v3.
```


To specify headers to be added to your endpoints, use the `apply.headers` section of the route group in `scribe.php`. For instance, if you have this config:

```php
  'routes' => [
    [
      'match' => [
        'domains' => ['*'],
        'prefixes' => ['v2/'],
      ],
      'apply' => [
        'headers' => [ 'Api-Version' => 'v2']
      ]
    ]
  ]
```

All endpoints that start with `v2/` will have the header `Api-Version: v2` included in their example requests and response calls.

Alternatively, you can use the `@header` doc block tag, in the format `@header <name> <optional example>`:

```php
/**
 * @header X-Api-Version v1
 */

```
