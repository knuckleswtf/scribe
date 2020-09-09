## {{ $route['metadata']['title'] ?: $route['uri']}}

@component('scribe::components.badges.auth', ['authenticated' => $route['metadata']['authenticated']])
@endcomponent

{!! $route['metadata']['description'] ?: ''!!}

> Example request:

@foreach($settings['languages'] as $language)
@include("scribe::partials.example-requests.$language")

@endforeach

@if(in_array('GET',$route['methods']) || (isset($route['showresponse']) && $route['showresponse']))
@foreach($route['responses'] as $response)
> Example response ({{$response['description'] ?? $response['status']}}):

```json
@if(is_object($response['content']) || is_array($response['content']))
{!! json_encode($response['content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
@elseif(is_string($response['content']) && \Illuminate\Support\Str::startsWith($response['content'], "<<binary>>"))
<Binary data> - {{ str_replace("<<binary>>","",$response['content']) }}
@elseif($response['status'] == 204)
<Empty response>
@elseif(is_string($response['content']) && json_decode($response['content']) == null && $response['content'] !== null)
{{-- If response is a non-JSON string, just print it --}}
{!! $response['content'] !!}
@else
{!! json_encode(json_decode($response['content']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
@endif
```
@endforeach
@endif

### Request
@foreach($route['methods'] as $method)
@component('scribe::components.badges.http-method', ['method' => $method])@endcomponent **`{{$route['uri']}}`**

@endforeach
@if(count($route['urlParameters']))
<h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
@foreach($route['urlParameters'] as $attribute => $parameter)
<p>
@component('scribe::components.field-details', [
  'name' => $attribute,
  'type' => $parameter['type'] ?? 'string',
  'required' => $parameter['required'] ?? true,
  'description' => $parameter['description'],
])
@endcomponent
</p>
@endforeach
@endif
@if(count($route['queryParameters']))
<h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
@foreach($route['queryParameters'] as $attribute => $parameter)
<p>
@component('scribe::components.field-details', [
  'name' => $attribute,
  'type' => $parameter['type'] ?? 'string',
  'required' => $parameter['required'] ?? true,
  'description' => $parameter['description'],
])
@endcomponent
</p>
@endforeach
@endif
@if(count($route['nestedBodyParameters']))
<h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
@component('scribe::partials.body-parameters', ['parameters' => $route['nestedBodyParameters']])
@endcomponent
@endif

@if(count($route['responseFields'] ?? []))
<h4 class="fancy-heading-panel"><b>Response Fields</b></h4>
@foreach($route['responseFields'] as $name => $field)
<p>
@component('scribe::components.field-details', [
  'name' => $name,
  'type' => $field['type'],
  'required' => true,
  'description' => $field['description'],
])
@endcomponent
</p>
@endforeach
@endif
