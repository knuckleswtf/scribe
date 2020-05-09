```bash
curl -X {{$route['methods'][0]}} \
    {{$route['methods'][0] == 'GET' ? '-G ' : ''}}"{{ rtrim($baseUrl, '/')}}/{{ ltrim($route['boundUri'], '/') }}@if(count($route['cleanQueryParameters']))?{!! \Knuckles\Scribe\Tools\WritingUtils::printQueryParamsAsString($route['cleanQueryParameters']) !!}@endif" @if(count($route['headers']))\
@foreach($route['headers'] as $header => $value)
    -H "{{$header}}: {{ addslashes($value) }}"@if(! ($loop->last) || ($loop->last && count($route['bodyParameters']))) \
@endif
@endforeach
@endif
@if(count($route['fileParameters']))
@foreach($route['cleanBodyParameters'] as $parameter => $value)
@foreach( \Knuckles\Scribe\Tools\WritingUtils::getParameterNamesAndValuesForFormData($parameter,$value) as $key => $actualValue)
    -F "{!! "$key=".$actualValue !!}" \
@endforeach
@endforeach
@foreach($route['fileParameters'] as $parameter => $file)
    -F "{!! "$parameter=@".$file->path() !!}" @if(! ($loop->last))\@endif
@endforeach
@elseif(count($route['cleanBodyParameters']))
    -d '{!! json_encode($route['cleanBodyParameters']) !!}'
@endif

```
