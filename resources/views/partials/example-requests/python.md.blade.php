@php
  use Knuckles\Scribe\Tools\WritingUtils as u;
  /** @var  Knuckles\Camel\Output\OutputEndpointData $endpoint */
@endphp
```python
import requests
import json

url = '{{ rtrim($baseUrl, '/') }}/{{ $endpoint->boundUri }}'
@if($endpoint->hasFiles() || (isset($endpoint->headers['Content-Type']) && $endpoint->headers['Content-Type'] == 'multipart/form-data' && count($endpoint->cleanBodyParameters)))
files = {
@foreach($endpoint->cleanBodyParameters as $parameter => $value)
@foreach(u::getParameterNamesAndValuesForFormData($parameter, $value) as $key => $actualValue)
  '{!! $key !!}': (None, '{!! $actualValue !!}')@if(!($loop->parent->last) || count($endpoint->fileParameters)),
@endif
@endforeach
@endforeach
@foreach($endpoint->fileParameters as $parameter => $value)
@foreach(u::getParameterNamesAndValuesForFormData($parameter, $value) as $key => $file)
  '{!! $key !!}': open('{!! $file->path() !!}', 'rb')@if(!($loop->parent->last)),
@endif
@endforeach
@endforeach
}
@endif
@if(count($endpoint->cleanBodyParameters))
payload = {!! json_encode($endpoint->cleanBodyParameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
@endif
@if(count($endpoint->cleanQueryParameters))
params = {!! u::printQueryParamsAsKeyValue($endpoint->cleanQueryParameters, "'", ":", 2, "{}") !!}
@endif
@if(!empty($endpoint->headers))
headers = {
@foreach($endpoint->headers as $header => $value)
  '{{$header}}': '{{$value}}'@if(!($loop->last)),
@endif
@endforeach

}

@endif
@php
$optionalArguments = [];
if (count($endpoint->headers)) $optionalArguments[] = "headers=headers";
if (count($endpoint->fileParameters)) $optionalArguments[] = "files=files";
if (count($endpoint->cleanBodyParameters) && $endpoint->headers['Content-Type'] != 'multipart/form-data') $optionalArguments[] = (count($endpoint->fileParameters) || $endpoint->headers['Content-Type'] == 'application/x-www-form-urlencoded' ? "data=payload" : "json=payload");
if (count($endpoint->cleanQueryParameters)) $optionalArguments[] = "params=params";
$optionalArguments = implode(', ',$optionalArguments);
@endphp
response = requests.request('{{$endpoint->httpMethods[0]}}', url, {{ $optionalArguments }})
response.json()
```
