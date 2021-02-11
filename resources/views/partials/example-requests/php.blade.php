```php

$client = new \GuzzleHttp\Client();
@if($hasRequestOptions)
$response = $client->{{ strtolower($route['methods'][0]) }}(
    '{{ rtrim($baseUrl, '/') . '/' . ltrim($route['boundUri'], '/') }}',
    [
@if(!empty($route['headers']))@php
// We don't need the Content-Type header because Guzzle sets it automatically when you use json or multipart.
unset($route['headers']['Content-Type']);
@endphp
        'headers' => {!! \Knuckles\Scribe\Tools\WritingUtils::printPhpValue($route['headers'], 8) !!},
@endif
@if(!empty($route['cleanQueryParameters']))
        'query' => {!! \Knuckles\Scribe\Tools\WritingUtils::printQueryParamsAsKeyValue($route['cleanQueryParameters'], "'", "=>", 12, "[]", 8) !!},
@endif
@if(count($route['fileParameters']))
        'multipart' => [
@foreach($route['cleanBodyParameters'] as $parameter => $value)
@foreach(\Knuckles\Scribe\Tools\WritingUtils::getParameterNamesAndValuesForFormData($parameter, $value) as $key => $actualValue)
            [
                'name' => '{!! $key !!}',
                'contents' => '{!! $actualValue !!}'
            ],
@endforeach
@endforeach
@foreach($route['fileParameters'] as $parameter => $value)
@foreach(\Knuckles\Scribe\Tools\WritingUtils::getParameterNamesAndValuesForFormData($parameter, $value) as $key => $file)
            [
                'name' => '{!!  $key !!}',
                'contents' => fopen('{!! $file->path() !!}', 'r')
            ],
@endforeach
@endforeach
        ],
@elseif(!empty($route['cleanBodyParameters']))
        'json' => {!! \Knuckles\Scribe\Tools\WritingUtils::printPhpValue($route['cleanBodyParameters'], 8) !!},
@endif
    ]
);
@else
$response = $client->{{ strtolower($route['methods'][0]) }}('{{ rtrim($baseUrl, '/') . '/' . ltrim($route['boundUri'], '/') }}');
@endif
$body = $response->getBody();
print_r(json_decode((string) $body));
```
