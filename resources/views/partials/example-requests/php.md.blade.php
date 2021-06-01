@php
    use Knuckles\Scribe\Tools\WritingUtils as u;
    /** @var  Knuckles\Camel\Output\OutputEndpointData $endpoint */
@endphp
```php
$client = new \GuzzleHttp\Client();
@if($endpoint->hasHeadersOrQueryOrBodyParams())
$response = $client->{{ strtolower($endpoint->httpMethods[0]) }}(
    '{{ rtrim($baseUrl, '/') . '/' . ltrim($endpoint->boundUri, '/') }}',
    [
@if(!empty($endpoint->headers))@php
// We don't need the Content-Type header because Guzzle sets it automatically when you use json or multipart.
unset($endpoint->headers['Content-Type']);
@endphp
        'headers' => {!! u::printPhpValue($endpoint->headers, 8) !!},
@endif
@if(!empty($endpoint->cleanQueryParameters))
        'query' => {!! u::printQueryParamsAsKeyValue($endpoint->cleanQueryParameters, "'", "=>", 12, "[]", 8) !!},
@endif
@if($endpoint->hasFiles())
        'multipart' => [
@foreach($endpoint->cleanBodyParameters as $parameter => $value)
@foreach(u::getParameterNamesAndValuesForFormData($parameter, $value) as $key => $actualValue)
            [
                'name' => '{!! $key !!}',
                'contents' => '{!! $actualValue !!}'
            ],
@endforeach
@endforeach
@foreach($endpoint->fileParameters as $parameter => $value)
@foreach(u::getParameterNamesAndValuesForFormData($parameter, $value) as $key => $file)
            [
                'name' => '{!!  $key !!}',
                'contents' => fopen('{!! $file->path() !!}', 'r')
            ],
@endforeach
@endforeach
        ],
@elseif(!empty($endpoint->cleanBodyParameters))
        'json' => {!! u::printPhpValue($endpoint->cleanBodyParameters, 8) !!},
@endif
    ]
);
@else
$response = $client->{{ strtolower($endpoint->httpMethods[0]) }}('{{ rtrim($baseUrl, '/') . '/' . ltrim($endpoint->boundUri, '/') }}');
@endif
$body = $response->getBody();
print_r(json_decode((string) $body));
```