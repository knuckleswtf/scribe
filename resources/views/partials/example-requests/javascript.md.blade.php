@php
    use Knuckles\Scribe\Tools\WritingUtils as u;
    /** @var  Knuckles\Camel\Output\OutputEndpointData $endpoint */
@endphp
```javascript
const url = new URL(
    "{{ rtrim($baseUrl, '/') }}/{{ ltrim($endpoint->boundUri, '/') }}"
);
@if(count($endpoint->cleanQueryParameters))

let params = {!! u::printQueryParamsAsKeyValue($endpoint->cleanQueryParameters, "\"", ":", 4, "{}") !!};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));
@endif

@if(!empty($endpoint->headers))
let headers = {
@foreach($endpoint->headers as $header => $value)
    "{{$header}}": "{{$value}}",
@endforeach
@empty($endpoint->headers['Accept'])
    "Accept": "application/json",
@endempty
};
@endif

@if($endpoint->hasFiles())
const body = new FormData();
@foreach($endpoint->cleanBodyParameters as $parameter => $value)
@foreach( u::getParameterNamesAndValuesForFormData($parameter, $value) as $key => $actualValue)
body.append('{!! $key !!}', '{!! $actualValue !!}');
@endforeach
@endforeach
@foreach($endpoint->fileParameters as $parameter => $value)
@foreach( u::getParameterNamesAndValuesForFormData($parameter, $value) as $key => $file)
body.append('{!! $key !!}', document.querySelector('input[name="{!! $key !!}"]').files[0]);
@endforeach
@endforeach
@elseif(count($endpoint->cleanBodyParameters))
let body = {!! json_encode($endpoint->cleanBodyParameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
@endif

fetch(url, {
    method: "{{$endpoint->httpMethods[0]}}",
@if(count($endpoint->headers))
    headers,
@endif
@if($endpoint->hasFiles())
    body,
@elseif(count($endpoint->cleanBodyParameters))
    body: JSON.stringify(body),
@endif
}).then(response => response.json());
```