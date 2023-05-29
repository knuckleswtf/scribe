@php
    use Knuckles\Scribe\Tools\Utils as u;
    /** @var  Knuckles\Camel\Output\OutputEndpointData $endpoint */
@endphp

<div class="sl-stack sl-stack--vertical sl-stack--8 HttpOperation sl-flex sl-flex-col sl-items-stretch sl-w-full">
    <div class="sl-stack sl-stack--vertical sl-stack--5 sl-flex sl-flex-col sl-items-stretch">
        <div class="sl-relative">
            <div class="sl-stack sl-stack--horizontal sl-stack--5 sl-flex sl-flex-row sl-items-center">
                <h2 class="sl-text-3xl sl-leading-tight sl-font-prose sl-text-heading sl-mt-5 sl-mb-1"
                    id="{!! $endpoint->fullSlug() !!}">
                    {{ $endpoint->name() }}
                </h2>
            </div>
        </div>

        <div class="sl-relative">
            <div title="{{ rtrim($baseUrl, '/') . '/'. ltrim($endpoint->uri, '/') }}"
                     class="sl-stack sl-stack--horizontal sl-stack--3 sl-inline-flex sl-flex-row sl-items-center sl-max-w-full sl-font-mono sl-py-2 sl-pr-4 sl-bg-canvas-50 sl-rounded-lg"
                >
                    @foreach($endpoint->httpMethods as $method)
                        <div class="sl-text-lg sl-font-semibold sl-px-2.5 sl-py-1 sl-text-on-primary sl-rounded-lg"
                             style="background-color: {{ \Knuckles\Scribe\Tools\WritingUtils::$httpMethodToCssColour[$method] }};"
                        >
                            {{ $method }}
                        </div>
                    @endforeach
                    <div class="sl-flex sl-overflow-x-hidden sl-text-lg sl-select-all">
                        <div dir="rtl"
                             class="sl-overflow-x-hidden sl-truncate sl-text-muted">{{ rtrim($baseUrl, '/') }}</div>
                        <div class="sl-flex-1 sl-font-semibold">/{{ ltrim($endpoint->uri, '/') }}</div>
                    </div>

                        @if($endpoint->metadata->authenticated)
                            <div class="sl-font-prose sl-font-semibold sl-px-1.5 sl-py-0.5 sl-text-on-primary sl-rounded-lg"
                                 style="background-color: darkred"
                            >requires authentication
                            </div>
                        @endif
            </div>
        </div>

        {!! Parsedown::instance()->text($endpoint->metadata->description ?: '') !!}
    </div>
    <div class="sl-flex">
        <div data-testid="two-column-left" class="sl-flex-1 sl-w-0">
            <div class="sl-stack sl-stack--vertical sl-stack--10 sl-flex sl-flex-col sl-items-stretch">
                <div class="sl-stack sl-stack--vertical sl-stack--8 sl-flex sl-flex-col sl-items-stretch">
                    @if(count($endpoint->headers))
                        <div class="sl-stack sl-stack--vertical sl-stack--5 sl-flex sl-flex-col sl-items-stretch">
                            <h3 class="sl-text-2xl sl-leading-snug sl-font-prose">
                                {{ u::trans("scribe::endpoint.headers") }}
                            </h3>
                            <div class="sl-text-sm">
                                @foreach($endpoint->headers as $header => $value)
                                    @component('scribe::themes.elements.components.field-details', [
                                      'name' => $header,
                                      'type' => null,
                                      'required' => false,
                                      'description' => null,
                                      'example' => $value,
                                      'endpointId' => $endpoint->endpointId(),
                                      'component' => 'header',
                                      'isInput' => true,
                                    ])
                                    @endcomponent
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(count($endpoint->urlParameters))
                        <div class="sl-stack sl-stack--vertical sl-stack--6 sl-flex sl-flex-col sl-items-stretch">
                            <h3 class="sl-text-2xl sl-leading-snug sl-font-prose">{{ u::trans("scribe::endpoint.url_parameters") }}</h3>

                            <div class="sl-text-sm">
                                @foreach($endpoint->urlParameters as $attribute => $parameter)
                                    @component('scribe::themes.elements.components.field-details', [
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
                                @endforeach
                            </div>
                        </div>
                    @endif


                    @if(count($endpoint->queryParameters))
                            <div class="sl-stack sl-stack--vertical sl-stack--6 sl-flex sl-flex-col sl-items-stretch">
                                <h3 class="sl-text-2xl sl-leading-snug sl-font-prose">{{ u::trans("scribe::endpoint.query_parameters") }}</h3>

                                <div class="sl-text-sm">
                                    @foreach($endpoint->queryParameters as $attribute => $parameter)
                                        @component('scribe::themes.elements.components.field-details', [
                                          'name' => $parameter->name,
                                          'type' => $parameter->type,
                                          'required' => $parameter->required,
                                          'description' => $parameter->description,
                                          'example' => $parameter->example ?? '',
                                          'endpointId' => $endpoint->endpointId(),
                                          'component' => 'query',
                                          'isInput' => true,
                                        ])
                                        @endcomponent
                                    @endforeach
                            </div>
                        </div>
                    @endif

                    @if(count($endpoint->nestedBodyParameters))
                        <div class="sl-stack sl-stack--vertical sl-stack--6 sl-flex sl-flex-col sl-items-stretch">
                            <h3 class="sl-text-2xl sl-leading-snug sl-font-prose">{{ u::trans("scribe::endpoint.body_parameters") }}</h3>

                                <div class="sl-text-sm">
                                    @component('scribe::themes.elements.components.nested-fields', [
                                      'fields' => $endpoint->nestedBodyParameters,
                                      'endpointId' => $endpoint->endpointId(),
                                    ])
                                    @endcomponent
                            </div>
                        </div>
                    @endif

                    @if(count($endpoint->responseFields))
                            <div class="sl-stack sl-stack--vertical sl-stack--6 sl-flex sl-flex-col sl-items-stretch">
                                <h3 class="sl-text-2xl sl-leading-snug sl-font-prose">{{ u::trans("scribe::endpoint.response_fields") }}</h3>

                                <div class="sl-text-sm">
                                    @component('scribe::themes.elements.components.nested-fields', [
                                      'fields' => $endpoint->nestedResponseFields,
                                      'endpointId' => $endpoint->endpointId(),
                                      'isInput' => false,
                                    ])
                                    @endcomponent
                                </div>
                            </div>
                        @endif
                </div>
            </div>
        </div>

        <div data-testid="two-column-right" class="sl-relative sl-w-2/5 sl-ml-16" style="max-width: 500px;">
            <div class="sl-stack sl-stack--vertical sl-stack--6 sl-flex sl-flex-col sl-items-stretch">

                @if($metadata['try_it_out']['enabled'] ?? false)
                    @include("scribe::themes.elements.try_it_out")
                @endif

                    @if($metadata['example_languages'])
                        <div class="sl-panel sl-outline-none sl-w-full sl-rounded-lg">
                            <div class="sl-panel__titlebar sl-flex sl-items-center sl-relative focus:sl-z-10 sl-text-base sl-leading-none sl-pr-3 sl-pl-4 sl-bg-canvas-200 sl-text-body sl-border-input focus:sl-border-primary sl-select-none">
                                <div class="sl-flex sl-flex-1 sl-items-center sl-h-lg">
                                    <div class="sl--ml-2">
                                        {{ u::trans("scribe::endpoint.example_request") }}:
                                        <select class="example-request-lang-toggle sl-text-base"
                                                aria-label="Request Sample Language"
                                                onchange="switchExampleLanguage(event.target.value);">
                                            @foreach($metadata['example_languages'] as $language)
                                                <option>{{ $language }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            @foreach($metadata['example_languages'] as $index => $language)
                                <div class="sl-bg-canvas-100 example-request example-request-{{ $language }}"
                                     style="{{ $index == 0 ? '' : 'display: none;' }}">
                                    <div class="sl-px-0 sl-py-1">
                                        <div style="max-height: 400px;" class="sl-rounded">
                                            @include("scribe::partials.example-requests.$language")
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($endpoint->isGet() || $endpoint->hasResponses())
                        <div class="sl-panel sl-outline-none sl-w-full sl-rounded-lg">
                            <div class="sl-panel__titlebar sl-flex sl-items-center sl-relative focus:sl-z-10 sl-text-base sl-leading-none sl-pr-3 sl-pl-4 sl-bg-canvas-200 sl-text-body sl-border-input focus:sl-border-primary sl-select-none">
                                <div class="sl-flex sl-flex-1 sl-items-center sl-py-2">
                                    <div class="sl--ml-2">
                                        <div class="sl-h-sm sl-text-base sl-font-medium sl-px-1.5 sl-text-muted sl-rounded sl-border-transparent sl-border">
                                            <div class="sl-mb-2 sl-inline-block">{{ u::trans("scribe::endpoint.example_response") }}:</div>
                                            <div class="sl-mb-2 sl-inline-block">
                                                <select
                                                        class="example-response-{{ $endpoint->endpointId() }}-toggle sl-text-base"
                                                        aria-label="Response sample"
                                                        onchange="switchExampleResponse('{{ $endpoint->endpointId() }}', event.target.value);">
                                                    @foreach($endpoint->responses as $index => $response)
                                                        <option value="{{ $index }}">{{ $response->fullDescription() }}</option>
                                                    @endforeach
                                                </select></div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button"
                                        class="sl-button sl-h-sm sl-text-base sl-font-medium sl-px-1.5 hover:sl-bg-canvas-50 active:sl-bg-canvas-100 sl-text-muted hover:sl-text-body focus:sl-text-body sl-rounded sl-border-transparent sl-border disabled:sl-opacity-70">
                                    <div class="sl-mx-0">
                                        <svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="copy"
                                             class="svg-inline--fa fa-copy fa-fw fa-sm sl-icon" role="img"
                                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                                            <path fill="currentColor"
                                                  d="M384 96L384 0h-112c-26.51 0-48 21.49-48 48v288c0 26.51 21.49 48 48 48H464c26.51 0 48-21.49 48-48V128h-95.1C398.4 128 384 113.6 384 96zM416 0v96h96L416 0zM192 352V128h-144c-26.51 0-48 21.49-48 48v288c0 26.51 21.49 48 48 48h192c26.51 0 48-21.49 48-48L288 416h-32C220.7 416 192 387.3 192 352z"></path>
                                        </svg>
                                    </div>
                                </button>
                            </div>
                            @foreach($endpoint->responses as $index => $response)
                                <div class="sl-panel__content-wrapper sl-bg-canvas-100 example-response-{{ $endpoint->endpointId() }} example-response-{{ $endpoint->endpointId() }}-{{ $index }}"
                                     style=" {{ $index == 0 ? '' : 'display: none;' }}"
                                >
                                    <div class="sl-panel__content sl-p-0">@if(count($response->headers))
                                            <details class="sl-pl-2">
                                                <summary style="cursor: pointer; list-style: none;">
                                                    <small>
                                                        <span class="expansion-chevrons">

    <svg aria-hidden="true" focusable="false" data-prefix="fas"
         data-icon="chevron-right"
         class="svg-inline--fa fa-chevron-right fa-fw sl-icon sl-text-muted"
         xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512">
        <path fill="currentColor"
              d="M96 480c-8.188 0-16.38-3.125-22.62-9.375c-12.5-12.5-12.5-32.75 0-45.25L242.8 256L73.38 86.63c-12.5-12.5-12.5-32.75 0-45.25s32.75-12.5 45.25 0l192 192c12.5 12.5 12.5 32.75 0 45.25l-192 192C112.4 476.9 104.2 480 96 480z"></path>
    </svg>
                                                            </span>
                                                        Headers
                                                    </small>
                                                </summary>
                                                <pre><code class="language-http">@foreach($response->headers as $header => $value)
                                                            {{ $header }}
                                                            : {{ is_array($value) ? implode('; ', $value) : $value }}
                                                        @endforeach </code></pre>
                                            </details>
                                        @endif
                                        @if(is_string($response->content) && Str::startsWith($response->content, "<<binary>>"))
                                            <pre><code>[{{ u::trans("scribe::endpoint.responses.binary") }}] - {{ htmlentities(str_replace("<<binary>>", "", $response->content)) }}</code></pre>
                                        @elseif($response->status == 204)
                                            <pre><code>[{{ u::trans("scribe::endpoint.responses.empty") }}]</code></pre>
                                        @else
                                            @php($parsed = json_decode($response->content))
                                            {{-- If response is a JSON string, prettify it. Otherwise, just print it --}}
                                            <pre><code style="max-height: 300px;"
                                                       class="language-json sl-overflow-x-auto sl-overflow-y-auto">{!! htmlentities($parsed != null ? json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $response->content) !!}</code></pre>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
        </div>
    </div>
</div>

