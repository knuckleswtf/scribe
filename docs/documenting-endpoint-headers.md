# Documenting headers for endpoints

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
