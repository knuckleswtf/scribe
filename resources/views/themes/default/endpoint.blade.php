@php
    /** @var  Knuckles\Camel\Output\OutputEndpointData $endpoint */
@endphp

<h2 id="{!! $endpoint->fullSlug() !!}">{{ $endpoint->name() }}</h2>

<p>
@component('scribe::components.badges.auth', ['authenticated' => $endpoint->isAuthed()])
@endcomponent
</p>

{!! Parsedown::instance()->text($endpoint->metadata->description ?: '') !!}

<span id="example-requests-{!! $endpoint->endpointId() !!}">
<blockquote>Example request:</blockquote>

@foreach($metadata['example_languages'] as $language)

<div class="{{ $language }}-example">
    @include("scribe::partials.example-requests.$language")
</div>

@endforeach
</span>

<span id="example-responses-{!! $endpoint->endpointId() !!}">
@if($endpoint->isGet() || $endpoint->hasResponses())
    @foreach($endpoint->responses as $response)
        <blockquote>
            <p>Example response ({{ $response->fullDescription() }}):</p>
        </blockquote>
        @if(count($response->headers))
        <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">@foreach($response->headers as $header => $value)
{{ $header }}: {{ is_array($value) ? implode('; ', $value) : $value }}
@endforeach </code></pre></details> @endif
        <pre>
@if(is_string($response->content) && Str::startsWith($response->content, "<<binary>>"))
<code>[Binary data] - {{ htmlentities(str_replace("<<binary>>", "", $response->content)) }}</code>
@elseif($response->status == 204)
<code>[Empty response]</code>
@else
@php($parsed = json_decode($response->content))
{{-- If response is a JSON string, prettify it. Otherwise, just print it --}}
<code class="language-json" style="max-height: 300px;">{!! htmlentities($parsed != null ? json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $response->content) !!}</code>
@endif </pre>
    @endforeach
@endif
</span>
<span id="execution-results-{{ $endpoint->endpointId() }}" hidden>
    <blockquote>Received response<span
                id="execution-response-status-{{ $endpoint->endpointId() }}"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-{{ $endpoint->endpointId() }}" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-{{ $endpoint->endpointId() }}" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-{{ $endpoint->endpointId() }}"></code></pre>
</span>
<form id="form-{{ $endpoint->endpointId() }}" data-method="{{ $endpoint->httpMethods[0] }}"
      data-path="{{ $endpoint->uri }}"
      data-authed="{{ $endpoint->isAuthed() ? 1 : 0 }}"
      data-hasfiles="{{ $endpoint->hasFiles() ? 1 : 0 }}"
      data-isarraybody="{{ $endpoint->isArrayBody() ? 1 : 0 }}"
      autocomplete="off"
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
                    onclick="cancelTryOut('{{ $endpoint->endpointId() }}');" hidden>Cancel 🛑
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
    @if(count($endpoint->headers))
        <h4 class="fancy-heading-panel"><b>Headers</b></h4>
        @foreach($endpoint->headers as $name => $example)
            <?php
                $htmlOptions = [];
                if ($endpoint->isAuthed() && $metadata['auth']['location'] == 'header' && $metadata['auth']['name'] == $name) {
                  $htmlOptions = [ 'class' => 'auth-value', ];
                  }
            ?>
            <div style="padding-left: 28px; clear: unset;">
                @component('scribe::components.field-details', [
                  'name' => $name,
                  'type' => null,
                  'required' => true,
                  'description' => null,
                  'example' => $example,
                  'endpointId' => $endpoint->endpointId(),
                  'component' => 'header',
                  'isInput' => true,
                  'html' => $htmlOptions,
                ])
                @endcomponent
            </div>
        @endforeach
    @endif
    @if(count($endpoint->urlParameters))
        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
        @foreach($endpoint->urlParameters as $attribute => $parameter)
            <div style="padding-left: 28px; clear: unset;">
                @component('scribe::components.field-details', [
                  'name' => $parameter->name,
                  'type' => $parameter->type ?? 'string',
                  'required' => $parameter->required,
                  'description' => $parameter->description,
                  'example' => $parameter->example ?? '',
                  'endpointId' => $endpoint->endpointId(),
                  'component' => 'url',
                  'isInput' => true,
                ])
                @endcomponent
            </div>
        @endforeach
    @endif
    @if(count($endpoint->queryParameters))
        <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
        @foreach($endpoint->queryParameters as $attribute => $parameter)
                <?php
                $htmlOptions = [];
                if ($endpoint->isAuthed() && $metadata['auth']['location'] == 'query' && $metadata['auth']['name'] == $attribute) {
                    $htmlOptions = [ 'class' => 'auth-value', ];
                }
                ?>
            <div style="padding-left: 28px; clear: unset;">
                @component('scribe::components.field-details', [
                  'name' => $parameter->name,
                  'type' => $parameter->type,
                  'required' => $parameter->required,
                  'description' => $parameter->description,
                  'example' => $parameter->example ?? '',
                  'endpointId' => $endpoint->endpointId(),
                  'component' => 'query',
                  'isInput' => true,
                  'html' => $htmlOptions,
                ])
                @endcomponent
            </div>
        @endforeach
    @endif
    @if(count($endpoint->nestedBodyParameters))
        <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <x-scribe::nested-fields
                :fields="$endpoint->nestedBodyParameters" :endpointId="$endpoint->endpointId()"
        />
    @endif
</form>

@if(count($endpoint->responseFields))
    <h3>Response</h3>
    <h4 class="fancy-heading-panel"><b>Response Fields</b></h4>
    <x-scribe::nested-fields
            :fields="$endpoint->nestedResponseFields" :endpointId="$endpoint->endpointId()"
            :isInput="false"
    />
@endif
