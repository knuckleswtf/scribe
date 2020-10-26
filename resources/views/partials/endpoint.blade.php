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
@elseif(is_string($response['content']) && \Str::startsWith($response['content'], "<<binary>>"))
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
<div id="execution-results-{{ $endpointId }}" hidden>
    <blockquote>Received response<span id="execution-response-status-{{ $endpointId }}"></span>:</blockquote>
    <pre class="json"><code id="execution-response-content-{{ $endpointId }}"></code></pre>
</div>
<div id="execution-error-{{ $endpointId }}" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-{{ $endpointId }}"></code></pre>
</div>
<form id="form-{{ $endpointId }}" data-method="{{ $route['methods'][0] }}" data-path="{{ $route['uri'] }}" data-authed="{{ $route['metadata']['authenticated'] ? 1 : 0 }}" data-hasfiles="{{ count($route['fileParameters']) }}" data-headers='@json($route['headers'])' onsubmit="event.preventDefault(); executeTryOut('{{ $endpointId }}', this);">
<h3>
    Request&nbsp;&nbsp;&nbsp;
    @if($settings['interactive'])
    <button type="button" style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;" id="btn-tryout-{{ $endpointId }}" onclick="tryItOut('{{ $endpointId }}');">Try it out ⚡</button>
    <button type="button" style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;" id="btn-canceltryout-{{ $endpointId }}" onclick="cancelTryOut('{{ $endpointId }}');" hidden>Cancel</button>&nbsp;&nbsp;
    <button type="submit" style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;" id="btn-executetryout-{{ $endpointId }}" hidden>Send Request 💥</button>
    @endif
</h3>
@foreach($route['methods'] as $method)
<p>
@component('scribe::components.badges.http-method', ['method' => $method])@endcomponent <b><code>{{$route['uri']}}</code></b>
</p>
@endforeach
@if($route['metadata']['authenticated'] && $auth['location'] === 'header')
<p>
<label id="auth-{{ $endpointId }}" hidden>{{ $auth['name'] }} header: <b><code>{{ $auth['prefix'] }}</code></b><input type="text" name="{{ $auth['name'] }}" data-prefix="{{ $auth['prefix'] }}" data-endpoint="{{ $endpointId }}" data-component="header"></label>
</p>
@endif
@if(count($route['urlParameters']))
<h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
@foreach($route['urlParameters'] as $attribute => $parameter)
<p>
@component('scribe::components.field-details', [
  'name' => $parameter['name'],
  'type' => $parameter['type'] ?? 'string',
  'required' => $parameter['required'] ?? true,
  'description' => $parameter['description'],
  'endpointId' => $endpointId,
  'component' => 'url',
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
  'name' => $parameter['name'],
  'type' => $parameter['type'] ?? 'string',
  'required' => $parameter['required'] ?? true,
  'description' => $parameter['description'],
  'endpointId' => $endpointId,
  'component' => 'query',
])
@endcomponent
</p>
@endforeach
@endif
@if(count($route['nestedBodyParameters']))
<h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
@component('scribe::partials.body-parameters', ['parameters' => $route['nestedBodyParameters'], 'endpointId' => $endpointId,])
@endcomponent
@endif
</form>

@if(count($route['responseFields'] ?? []))
### Response
<h4 class="fancy-heading-panel"><b>Response Fields</b></h4>
@foreach($route['responseFields'] as $name => $field)
<p>
@component('scribe::components.field-details', [
  'name' => $field['name'],
  'type' => $field['type'],
  'required' => true,
  'description' => $field['description'],
  'isInput' => false,
])
@endcomponent
</p>
@endforeach
@endif
