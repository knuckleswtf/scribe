@php
    /** @var  Knuckles\Camel\Output\OutputEndpointData $endpoint */
@endphp

<h2 id="{!! Str::slug($group['name']) !!}-{!! $endpoint->endpointId() !!}">{{ $endpoint->metadata->title ?: $endpoint->uri}}</h2>

<p>
@component('scribe::components.badges.auth', ['authenticated' => $endpoint->metadata->authenticated])
@endcomponent
</p>

{!! Parsedown::instance()->text($endpoint->metadata->description ?: '') !!}

<blockquote>Example request:</blockquote>

@foreach($metadata['example_languages'] as $language)

@include("scribe::partials.example-requests.$language")

@endforeach

@if($endpoint->isGet() || $endpoint->hasResponses())
    @foreach($endpoint->responses as $response)
        <blockquote>
            <p>Example response ({{$response->description ?: $response->status}}):</p>
        </blockquote>
        @if(count($response->headers))
        <details class="annotation">
            <summary>
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre>
            <code class="language-http">@foreach($response->headers as $header => $values)
{{ $header }}: {{ implode('; ', $values) }}
@endforeach </code>
            </pre>
        </details> @endif
        <pre>
                <code class="language-json">
@if(is_string($response->content) && Str::startsWith($response->content, "<<binary>>"))
[Binary data] - {{ htmlentities(str_replace("<<binary>>", "", $response->content)) }}
@elseif($response->status == 204)
[Empty response]
@elseif(is_string($response->content) && json_decode($response->content) == null && $response->content !== null)
{{-- If response is a non-JSON string, just print it --}}
{!! htmlentities($response->content) !!}
@else
{!! htmlentities(json_encode(json_decode($response->content), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !!}
@endif </code>
        </pre>
    @endforeach
@endif
<div id="execution-results-{{ $endpoint->endpointId() }}" hidden>
    <blockquote>Received response<span
                id="execution-response-status-{{ $endpoint->endpointId() }}"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-{{ $endpoint->endpointId() }}"></code></pre>
</div>
<div id="execution-error-{{ $endpoint->endpointId() }}" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-{{ $endpoint->endpointId() }}"></code></pre>
</div>
<form id="form-{{ $endpoint->endpointId() }}" data-method="{{ $endpoint->httpMethods[0] }}"
      data-path="{{ $endpoint->uri }}"
      data-authed="{{ $endpoint->metadata->authenticated ? 1 : 0 }}"
      data-hasfiles="{{ $endpoint->hasFiles() ? 1 : 0 }}"
      data-headers='@json($endpoint->headers)'
      onsubmit="event.preventDefault(); executeTryOut('{{ $endpoint->endpointId() }}', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
        @if($metadata['try_it_out']['enabled'] ?? false)
            <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-{{ $endpoint->endpointId() }}"
                    onclick="tryItOut('{{ $endpoint->endpointId() }}');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-{{ $endpoint->endpointId() }}"
                    onclick="cancelTryOut('{{ $endpoint->endpointId() }}');" hidden>Cancel
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-{{ $endpoint->endpointId() }}" hidden>Send Request 💥
            </button>
        @endif
    </h3>
    @foreach($endpoint->httpMethods as $method)
        <p>
            @component('scribe::components.badges.http-method', ['method' => $method])@endcomponent
            <b><code>{{$endpoint->uri}}</code></b>
        </p>
    @endforeach
    @if($endpoint->metadata->authenticated && $metadata['auth']['location'] === 'header')
        <p>
            <label id="auth-{{ $endpoint->endpointId() }}" hidden>{{ $metadata['auth']['name'] }} header:
                <b><code>{{ $metadata['auth']['prefix'] }}</code></b><input type="text"
                                                                name="{{ $metadata['auth']['name'] }}"
                                                                data-prefix="{{ $metadata['auth']['prefix'] }}"
                                                                data-endpoint="{{ $endpoint->endpointId() }}"
                                                                data-component="header"></label>
        </p>
    @endif
    @if(count($endpoint->urlParameters))
        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
        @foreach($endpoint->urlParameters as $attribute => $parameter)
            <p>
                @component('scribe::components.field-details', [
                  'name' => $parameter->name,
                  'type' => $parameter->type ?? 'string',
                  'required' => $parameter->required,
                  'description' => $parameter->description,
                  'endpointId' => $endpoint->endpointId(),
                  'component' => 'url',
                ])
                @endcomponent
            </p>
        @endforeach
    @endif
    @if(count($endpoint->queryParameters))
        <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
        @foreach($endpoint->queryParameters as $attribute => $parameter)
            <p>
                @component('scribe::components.field-details', [
                  'name' => $parameter->name,
                  'type' => $parameter->type,
                  'required' => $parameter->required,
                  'description' => $parameter->description,
                  'endpointId' => $endpoint->endpointId(),
                  'component' => 'query',
                ])
                @endcomponent
            </p>
        @endforeach
    @endif
    @if(count($endpoint->nestedBodyParameters))
        <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        @component('scribe::components.body-parameters', ['parameters' => $endpoint->nestedBodyParameters, 'endpointId' => $endpoint->endpointId(),])
        @endcomponent
    @endif
</form>

@if(count($endpoint->responseFields))
    <h3>Response</h3>
    <h4 class="fancy-heading-panel"><b>Response Fields</b></h4>
    @foreach($endpoint->responseFields as $name => $field)
        <p>
            @component('scribe::components.field-details', [
              'name' => $field->name,
              'type' => $field->type,
              'required' => true,
              'description' => $field->description,
              'isInput' => false,
            ])
            @endcomponent
        </p>
    @endforeach
@endif
