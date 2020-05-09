```javascript
const url = new URL(
    "{{ rtrim($baseUrl, '/') }}/{{ ltrim($route['boundUri'], '/') }}"
);
@if(count($route['cleanQueryParameters']))

let params = {!! \Knuckles\Scribe\Tools\WritingUtils::printQueryParamsAsKeyValue($route['cleanQueryParameters'], "\"", ":", 4, "{}") !!};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));
@endif

@if(!empty($route['headers']))
let headers = {
@foreach($route['headers'] as $header => $value)
    "{{$header}}": "{{$value}}",
@endforeach
@if(!array_key_exists('Accept', $route['headers']))
    "Accept": "application/json",
@endif
};
@endif

@if(count($route['fileParameters']))
const body = new FormData();
@foreach($route['cleanBodyParameters'] as $parameter => $value)
@foreach( \Knuckles\Scribe\Tools\WritingUtils::getParameterNamesAndValuesForFormData($parameter,$value) as $key => $actualValue)
body.append('{!! $key !!}', '{!! $actualValue !!}');
@endforeach
@endforeach
@foreach($route['fileParameters'] as $parameter => $file)
body.append('{!! $parameter !!}', document.querySelector('input[name="{!! $parameter !!}"]').files[0]);
@endforeach
@elseif(count($route['cleanBodyParameters']))
let body = {!! json_encode($route['cleanBodyParameters'], JSON_PRETTY_PRINT) !!}
@endif

fetch(url, {
    method: "{{$route['methods'][0]}}",
@if(count($route['headers']))
    headers: headers,
@endif
@if(count($route['fileParameters']) || count($route['cleanBodyParameters']))
    body: body
@endif
})
    .then(response => response.json())
    .then(json => console.log(json));
```
