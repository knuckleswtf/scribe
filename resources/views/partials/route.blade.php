<!-- START_{{$route['id']}} -->
@if($route['metadata']['title'] != '')## {{ $route['metadata']['title']}}
@else## {{$route['uri']}}@endif
@if($route['metadata']['authenticated'])
<small class="badge badge-darkred">REQUIRES AUTHENTICATION</small>@endif
@if($route['metadata']['description'])

{!! $route['metadata']['description'] !!}
@endif

> Example request:

@foreach($settings['languages'] as $language)
@include("scribe::partials.example-requests.$language")

@endforeach

@if(in_array('GET',$route['methods']) || (isset($route['showresponse']) && $route['showresponse']))
@foreach($route['responses'] as $response)
> Example response ({{$response['status']}}):

```json
@if(is_object($response['content']) || is_array($response['content']))
{!! json_encode($response['content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
@else
{!! json_encode(json_decode($response['content']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
@endif
```
@endforeach
@endif

### Request
@foreach($route['methods'] as $method)
<small class="badge badge-{{ \Knuckles\Scribe\Tools\Utils::$httpMethodToCssColour[$method] }}">{{$method}}</small> **`{{$route['uri']}}`**

@endforeach
@if(count($route['urlParameters']))
#### URL Parameters
@foreach($route['urlParameters'] as $attribute => $parameter)
<p>
    <code><b>{{ "{".$attribute."}" }}</b></code>&nbsp; @if(!$parameter['required'])<i>optional</i>@endif
    <br>
    {!! $parameter['description'] !!}
</p>
@endforeach
@endif
@if(count($route['queryParameters']))
#### Query Parameters
@foreach($route['queryParameters'] as $attribute => $parameter)
<p>
    <code><b>{{$attribute}}</b></code>&nbsp; @if(!$parameter['required'])<i>optional</i>@endif
    <br>
    {!! $parameter['description'] !!}
</p>
@endforeach
@endif
@if(count($route['bodyParameters']))
#### Body Parameters
@foreach($route['bodyParameters'] as $attribute => $parameter)
<p>
    <code><b>{{$attribute}}</b></code>&nbsp; <small>{{$parameter['type']}}</small> @if(!$parameter['required'])<i>optional</i>@endif
    <br>
    {!! $parameter['description'] !!}
</p>
    @endforeach
@endif

@if(count($route['responseFields'] ?? []))
### Response Fields
@foreach($route['responseFields'] as $attribute => $parameter)
<p>
    <code><b>{{$attribute}}</b></code>
    <br>
    {!! $parameter['description'] !!}
</p>
@endforeach
@endif

<!-- END_{{$route['id']}} -->
