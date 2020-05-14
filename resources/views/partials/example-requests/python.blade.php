```python
import requests
import json

url = '{{ rtrim($baseUrl, '/') }}/{{ $route['boundUri'] }}'
@if(count($route['fileParameters']))
files = {
@foreach($route['fileParameters'] as $name => $file)
  '{!! $name !!}': open('{!! $file->path() !!}', 'rb')@if(!($loop->last)),
@endif
@endforeach

}
@endif
@if(count($route['cleanBodyParameters']))
payload = {!! json_encode($route['cleanBodyParameters'], JSON_PRETTY_PRINT) !!}
@endif
@if(count($route['cleanQueryParameters']))
params = {!! \Knuckles\Scribe\Tools\WritingUtils::printQueryParamsAsKeyValue($route['cleanQueryParameters'], "'", ":", 2, "{}") !!}
@endif
@if(!empty($route['headers']))
headers = {
@foreach($route['headers'] as $header => $value)
  '{{$header}}': '{{$value}}'@if(!($loop->last)),
@endif
@endforeach

}

@endif
@php
$optionalArguments = [];
if (count($route['headers'])) $optionalArguments[] = "headers=headers";
if (count($route['fileParameters'])) $optionalArguments[] = "files=files";
if (count($route['cleanBodyParameters'])) $optionalArguments[] = (count($route['fileParameters']) ? "data=payload" : "json=payload");
if (count($route['cleanQueryParameters'])) $optionalArguments[] = "params=params";
$optionalArguments = implode(', ',$optionalArguments);
@endphp
response = requests.request('{{$route['methods'][0]}}', url, {{ $optionalArguments }})
response.json()
```
