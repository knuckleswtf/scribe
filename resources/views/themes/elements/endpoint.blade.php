@php
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
                            <div class="sl-stack sl-stack--horizontal sl-stack--6 sl-flex sl-flex-row sl-items-center">
                                <h3 id="/paths/api-file-input/post#request-headers" aria-label="Headers"
                                    class="sl-link-heading sl-text-2xl sl-leading-snug sl-font-prose sl-font-semibold sl-text-heading">
                                    <a href="#/paths/api-file-input/post#request-headers"
                                       class="sl-link sl-link-heading__link sl-inline-flex sl-items-center sl-text-current">
                                        <div>Headers</div>
                                        <div class="sl-link-heading__icon sl-text-base sl-ml-4 sl-text-muted">
                                            <svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="link"
                                                 class="svg-inline--fa fa-link sl-icon" role="img"
                                                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512">
                                                <path fill="currentColor"
                                                      d="M172.5 131.1C228.1 75.51 320.5 75.51 376.1 131.1C426.1 181.1 433.5 260.8 392.4 318.3L391.3 319.9C381 334.2 361 337.6 346.7 327.3C332.3 317 328.9 297 339.2 282.7L340.3 281.1C363.2 249 359.6 205.1 331.7 177.2C300.3 145.8 249.2 145.8 217.7 177.2L105.5 289.5C73.99 320.1 73.99 372 105.5 403.5C133.3 431.4 177.3 435 209.3 412.1L210.9 410.1C225.3 400.7 245.3 404 255.5 418.4C265.8 432.8 262.5 452.8 248.1 463.1L246.5 464.2C188.1 505.3 110.2 498.7 60.21 448.8C3.741 392.3 3.741 300.7 60.21 244.3L172.5 131.1zM467.5 380C411 436.5 319.5 436.5 263 380C213 330 206.5 251.2 247.6 193.7L248.7 192.1C258.1 177.8 278.1 174.4 293.3 184.7C307.7 194.1 311.1 214.1 300.8 229.3L299.7 230.9C276.8 262.1 280.4 306.9 308.3 334.8C339.7 366.2 390.8 366.2 422.3 334.8L534.5 222.5C566 191 566 139.1 534.5 108.5C506.7 80.63 462.7 76.99 430.7 99.9L429.1 101C414.7 111.3 394.7 107.1 384.5 93.58C374.2 79.2 377.5 59.21 391.9 48.94L393.5 47.82C451 6.731 529.8 13.25 579.8 63.24C636.3 119.7 636.3 211.3 579.8 267.7L467.5 380z"></path>
                                            </svg>
                                        </div>
                                    </a></h3>
                            </div>
                            <div class="" id="mosaic-provider-react-aria-1-1">
                                <div data-overlay-container="true" class="">
                                    <div class="JsonSchemaViewer">
                                        <div></div>
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
                            </div>
                        </div>
                    @endif

                    @if(count($endpoint->urlParameters))
                        <div class="sl-stack sl-stack--vertical sl-stack--6 sl-flex sl-flex-col sl-items-stretch">
                            <div class="sl-stack sl-stack--horizontal sl-stack--6 sl-flex sl-flex-row sl-items-center">
                                <h3 id="/paths/api-file-input/post#request-body"
                                    class="sl-link-heading sl-text-2xl sl-leading-snug sl-font-prose sl-font-semibold sl-text-heading">
                                    <a href="#/paths/api-file-input/post#request-body"
                                       class="sl-link sl-link-heading__link sl-inline-flex sl-items-center sl-text-current">
                                        URL Parameters
                                        <div class="sl-link-heading__icon sl-text-base sl-ml-4 sl-text-muted">
                                            <svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="link"
                                                 class="svg-inline--fa fa-link sl-icon" role="img"
                                                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512">
                                                <path fill="currentColor"
                                                      d="M172.5 131.1C228.1 75.51 320.5 75.51 376.1 131.1C426.1 181.1 433.5 260.8 392.4 318.3L391.3 319.9C381 334.2 361 337.6 346.7 327.3C332.3 317 328.9 297 339.2 282.7L340.3 281.1C363.2 249 359.6 205.1 331.7 177.2C300.3 145.8 249.2 145.8 217.7 177.2L105.5 289.5C73.99 320.1 73.99 372 105.5 403.5C133.3 431.4 177.3 435 209.3 412.1L210.9 410.1C225.3 400.7 245.3 404 255.5 418.4C265.8 432.8 262.5 452.8 248.1 463.1L246.5 464.2C188.1 505.3 110.2 498.7 60.21 448.8C3.741 392.3 3.741 300.7 60.21 244.3L172.5 131.1zM467.5 380C411 436.5 319.5 436.5 263 380C213 330 206.5 251.2 247.6 193.7L248.7 192.1C258.1 177.8 278.1 174.4 293.3 184.7C307.7 194.1 311.1 214.1 300.8 229.3L299.7 230.9C276.8 262.1 280.4 306.9 308.3 334.8C339.7 366.2 390.8 366.2 422.3 334.8L534.5 222.5C566 191 566 139.1 534.5 108.5C506.7 80.63 462.7 76.99 430.7 99.9L429.1 101C414.7 111.3 394.7 107.1 384.5 93.58C374.2 79.2 377.5 59.21 391.9 48.94L393.5 47.82C451 6.731 529.8 13.25 579.8 63.24C636.3 119.7 636.3 211.3 579.8 267.7L467.5 380z"></path>
                                            </svg>
                                        </div>
                                    </a></h3>
                            </div>

                            <div data-overlay-container="true" class="">
                                <div class="JsonSchemaViewer">
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
                        </div>
                    @endif


                    @if(count($endpoint->queryParameters))
                        <div class="sl-stack sl-stack--vertical sl-stack--6 sl-flex sl-flex-col sl-items-stretch">
                            <div class="sl-stack sl-stack--horizontal sl-stack--6 sl-flex sl-flex-row sl-items-center">
                                <h3 id="/paths/api-file-input/post#request-body"
                                    class="sl-link-heading sl-text-2xl sl-leading-snug sl-font-prose sl-font-semibold sl-text-heading">
                                    <a href="#/paths/api-file-input/post#request-body"
                                       class="sl-link sl-link-heading__link sl-inline-flex sl-items-center sl-text-current">
                                        Query Parameters
                                        <div class="sl-link-heading__icon sl-text-base sl-ml-4 sl-text-muted">
                                            <svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="link"
                                                 class="svg-inline--fa fa-link sl-icon" role="img"
                                                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512">
                                                <path fill="currentColor"
                                                      d="M172.5 131.1C228.1 75.51 320.5 75.51 376.1 131.1C426.1 181.1 433.5 260.8 392.4 318.3L391.3 319.9C381 334.2 361 337.6 346.7 327.3C332.3 317 328.9 297 339.2 282.7L340.3 281.1C363.2 249 359.6 205.1 331.7 177.2C300.3 145.8 249.2 145.8 217.7 177.2L105.5 289.5C73.99 320.1 73.99 372 105.5 403.5C133.3 431.4 177.3 435 209.3 412.1L210.9 410.1C225.3 400.7 245.3 404 255.5 418.4C265.8 432.8 262.5 452.8 248.1 463.1L246.5 464.2C188.1 505.3 110.2 498.7 60.21 448.8C3.741 392.3 3.741 300.7 60.21 244.3L172.5 131.1zM467.5 380C411 436.5 319.5 436.5 263 380C213 330 206.5 251.2 247.6 193.7L248.7 192.1C258.1 177.8 278.1 174.4 293.3 184.7C307.7 194.1 311.1 214.1 300.8 229.3L299.7 230.9C276.8 262.1 280.4 306.9 308.3 334.8C339.7 366.2 390.8 366.2 422.3 334.8L534.5 222.5C566 191 566 139.1 534.5 108.5C506.7 80.63 462.7 76.99 430.7 99.9L429.1 101C414.7 111.3 394.7 107.1 384.5 93.58C374.2 79.2 377.5 59.21 391.9 48.94L393.5 47.82C451 6.731 529.8 13.25 579.8 63.24C636.3 119.7 636.3 211.3 579.8 267.7L467.5 380z"></path>
                                            </svg>
                                        </div>
                                    </a></h3>
                            </div>

                            <div data-overlay-container="true" class="">
                                <div class="JsonSchemaViewer">
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
                        </div>
                    @endif

                    @if(count($endpoint->nestedBodyParameters))
                        <div class="sl-stack sl-stack--vertical sl-stack--6 sl-flex sl-flex-col sl-items-stretch">
                            <div class="sl-stack sl-stack--horizontal sl-stack--6 sl-flex sl-flex-row sl-items-center">
                                <h3 id="/paths/api-file-input/post#request-body"
                                    class="sl-link-heading sl-text-2xl sl-leading-snug sl-font-prose sl-font-semibold sl-text-heading">
                                    <a href="#/paths/api-file-input/post#request-body"
                                       class="sl-link sl-link-heading__link sl-inline-flex sl-items-center sl-text-current">
                                        Body Parameters
                                        <div class="sl-link-heading__icon sl-text-base sl-ml-4 sl-text-muted">
                                            <svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="link"
                                                 class="svg-inline--fa fa-link sl-icon" role="img"
                                                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512">
                                                <path fill="currentColor"
                                                      d="M172.5 131.1C228.1 75.51 320.5 75.51 376.1 131.1C426.1 181.1 433.5 260.8 392.4 318.3L391.3 319.9C381 334.2 361 337.6 346.7 327.3C332.3 317 328.9 297 339.2 282.7L340.3 281.1C363.2 249 359.6 205.1 331.7 177.2C300.3 145.8 249.2 145.8 217.7 177.2L105.5 289.5C73.99 320.1 73.99 372 105.5 403.5C133.3 431.4 177.3 435 209.3 412.1L210.9 410.1C225.3 400.7 245.3 404 255.5 418.4C265.8 432.8 262.5 452.8 248.1 463.1L246.5 464.2C188.1 505.3 110.2 498.7 60.21 448.8C3.741 392.3 3.741 300.7 60.21 244.3L172.5 131.1zM467.5 380C411 436.5 319.5 436.5 263 380C213 330 206.5 251.2 247.6 193.7L248.7 192.1C258.1 177.8 278.1 174.4 293.3 184.7C307.7 194.1 311.1 214.1 300.8 229.3L299.7 230.9C276.8 262.1 280.4 306.9 308.3 334.8C339.7 366.2 390.8 366.2 422.3 334.8L534.5 222.5C566 191 566 139.1 534.5 108.5C506.7 80.63 462.7 76.99 430.7 99.9L429.1 101C414.7 111.3 394.7 107.1 384.5 93.58C374.2 79.2 377.5 59.21 391.9 48.94L393.5 47.82C451 6.731 529.8 13.25 579.8 63.24C636.3 119.7 636.3 211.3 579.8 267.7L467.5 380z"></path>
                                            </svg>
                                        </div>
                                    </a></h3>
                            </div>

                            <div data-overlay-container="true" class="">
                                <div class="JsonSchemaViewer">
                                    <div></div>
                                    @component('scribe::themes.elements.components.nested-fields', [
                                      'fields' => $endpoint->nestedBodyParameters,
                                      'endpointId' => $endpoint->endpointId(),
                                    ])
                                    @endcomponent
                                </div>
                            </div>
                        </div>
                    @endif

                    @if(count($endpoint->responseFields))
                        <div class="sl-stack sl-stack--vertical sl-stack--6 sl-flex sl-flex-col sl-items-stretch">
                            <div class="sl-stack sl-stack--horizontal sl-stack--6 sl-flex sl-flex-row sl-items-center">
                                <h3 id="/paths/api-file-input/post#request-body"
                                    class="sl-link-heading sl-text-2xl sl-leading-snug sl-font-prose sl-font-semibold sl-text-heading">
                                    <a href="#/paths/api-file-input/post#request-body"
                                       class="sl-link sl-link-heading__link sl-inline-flex sl-items-center sl-text-current">
                                        Response Fields
                                        <div class="sl-link-heading__icon sl-text-base sl-ml-4 sl-text-muted">
                                            <svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="link"
                                                 class="svg-inline--fa fa-link sl-icon" role="img"
                                                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512">
                                                <path fill="currentColor"
                                                      d="M172.5 131.1C228.1 75.51 320.5 75.51 376.1 131.1C426.1 181.1 433.5 260.8 392.4 318.3L391.3 319.9C381 334.2 361 337.6 346.7 327.3C332.3 317 328.9 297 339.2 282.7L340.3 281.1C363.2 249 359.6 205.1 331.7 177.2C300.3 145.8 249.2 145.8 217.7 177.2L105.5 289.5C73.99 320.1 73.99 372 105.5 403.5C133.3 431.4 177.3 435 209.3 412.1L210.9 410.1C225.3 400.7 245.3 404 255.5 418.4C265.8 432.8 262.5 452.8 248.1 463.1L246.5 464.2C188.1 505.3 110.2 498.7 60.21 448.8C3.741 392.3 3.741 300.7 60.21 244.3L172.5 131.1zM467.5 380C411 436.5 319.5 436.5 263 380C213 330 206.5 251.2 247.6 193.7L248.7 192.1C258.1 177.8 278.1 174.4 293.3 184.7C307.7 194.1 311.1 214.1 300.8 229.3L299.7 230.9C276.8 262.1 280.4 306.9 308.3 334.8C339.7 366.2 390.8 366.2 422.3 334.8L534.5 222.5C566 191 566 139.1 534.5 108.5C506.7 80.63 462.7 76.99 430.7 99.9L429.1 101C414.7 111.3 394.7 107.1 384.5 93.58C374.2 79.2 377.5 59.21 391.9 48.94L393.5 47.82C451 6.731 529.8 13.25 579.8 63.24C636.3 119.7 636.3 211.3 579.8 267.7L467.5 380z"></path>
                                            </svg>
                                        </div>
                                    </a></h3>
                            </div>

                            <div data-overlay-container="true" class="">
                                <div class="JsonSchemaViewer">
                                    <div></div>
                                    @component('scribe::themes.elements.components.nested-fields', [
                                      'fields' => $endpoint->nestedResponseFields,
                                      'endpointId' => $endpoint->endpointId(),
                                      'isInput' => false,
                                    ])
                                    @endcomponent
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div data-testid="two-column-right" class="sl-relative sl-w-2/5 sl-ml-16" style="max-width: 500px;">
            <div class="sl-stack sl-stack--vertical sl-stack--6 sl-flex sl-flex-col sl-items-stretch">
                <div class="sl-inverted">
                    <div class="sl-overflow-y-hidden sl-rounded-lg">
                        <div class="TryItPanel sl-bg-canvas-100 sl-rounded-lg">
                            <div class="sl-panel sl-outline-none sl-w-full">
                                <div aria-expanded="true" tabindex="0"
                                     class="sl-panel__titlebar sl-flex sl-items-center sl-relative focus:sl-z-10 sl-text-base sl-leading-none sl-pr-4 sl-pl-3 sl-bg-canvas-200 sl-text-body sl-border-input focus:sl-border-primary sl-cursor-pointer sl-select-none"
                                     role="button">
                                    <div class="sl-flex sl-flex-1 sl-items-center sl-h-lg">
                                        <div class="sl-flex sl-items-center sl-mr-1.5">
                                            <svg aria-hidden="true" focusable="false" data-prefix="fas"
                                                 data-icon="caret-down"
                                                 class="svg-inline--fa fa-caret-down fa-fw sl-icon" role="img"
                                                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512">
                                                <path fill="currentColor"
                                                      d="M310.6 246.6l-127.1 128C176.4 380.9 168.2 384 160 384s-16.38-3.125-22.63-9.375l-127.1-128C.2244 237.5-2.516 223.7 2.438 211.8S19.07 192 32 192h255.1c12.94 0 24.62 7.781 29.58 19.75S319.8 237.5 310.6 246.6z"></path>
                                            </svg>
                                        </div>
                                        Parameters
                                    </div>
                                </div>
                                <div class="sl-panel__content-wrapper sl-bg-canvas-100" role="region">
                                    <div class="sl-overflow-y-auto ParameterGrid OperationParametersContent sl-p-4">
                                        <label aria-hidden="true" data-testid="param-label" for="id_Accept_bvK8YIAi"
                                               class="sl-text-base">Accept</label><span class="sl-mx-3">:</span>
                                        <div>
                                            <div class="sl-flex sl-flex-1">
                                                <div class="sl-input sl-flex-1 sl-relative"><input
                                                            id="id_Accept_bvK8YIAi" aria-label="Accept"
                                                            placeholder="example: application/json" type="text"
                                                            aria-required="true"
                                                            class="sl-relative sl-w-full sl-h-md sl-text-base sl-pr-2.5 sl-pl-2.5 sl-rounded sl-border-transparent hover:sl-border-input focus:sl-border-primary sl-border"
                                                            value=""></div>
                                            </div>
                                        </div>
                                        <label aria-hidden="true" data-testid="param-label"
                                               for="id_Content-Type_sY6AWtnS"
                                               class="sl-text-base">Content-Type</label><span class="sl-mx-3">:</span>
                                        <div>
                                            <div class="sl-flex sl-flex-1">
                                                <div class="sl-input sl-flex-1 sl-relative"><input
                                                            id="id_Content-Type_sY6AWtnS" aria-label="Content-Type"
                                                            placeholder="example: multipart/form-data" type="text"
                                                            aria-required="true"
                                                            class="sl-relative sl-w-full sl-h-md sl-text-base sl-pr-2.5 sl-pl-2.5 sl-rounded sl-border-transparent hover:sl-border-input focus:sl-border-primary sl-border"
                                                            value=""></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="sl-panel sl-outline-none sl-w-full">
                                <div aria-expanded="true" tabindex="0"
                                     class="sl-panel__titlebar sl-flex sl-items-center sl-relative focus:sl-z-10 sl-text-base sl-leading-none sl-pr-4 sl-pl-3 sl-bg-canvas-200 sl-text-body sl-border-input focus:sl-border-primary sl-cursor-pointer sl-select-none"
                                     role="button">
                                    <div class="sl-flex sl-flex-1 sl-items-center sl-h-lg">
                                        <div class="sl-flex sl-items-center sl-mr-1.5">
                                            <svg aria-hidden="true" focusable="false" data-prefix="fas"
                                                 data-icon="caret-down"
                                                 class="svg-inline--fa fa-caret-down fa-fw sl-icon" role="img"
                                                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512">
                                                <path fill="currentColor"
                                                      d="M310.6 246.6l-127.1 128C176.4 380.9 168.2 384 160 384s-16.38-3.125-22.63-9.375l-127.1-128C.2244 237.5-2.516 223.7 2.438 211.8S19.07 192 32 192h255.1c12.94 0 24.62 7.781 29.58 19.75S319.8 237.5 310.6 246.6z"></path>
                                            </svg>
                                        </div>
                                        Body
                                    </div>
                                </div>
                                <div class="sl-panel__content-wrapper sl-bg-canvas-100" role="region">
                                    <div class="sl-overflow-y-auto ParameterGrid OperationParametersContent sl-p-4">
                                        <label aria-hidden="true" data-testid="param-label" for="id_the_file_YxeQBmS1">the_file*</label><span
                                                class="sl-mx-3">:</span>
                                        <div class="sl-flex sl-flex-1 sl-items-center">
                                            <div class="sl-input sl-flex-1 sl-relative sl-bg-canvas-100 sl-text-muted">
                                                <input disabled="" id="id_the_file_YxeQBmS1" aria-label="the_file"
                                                       placeholder="pick a file" type="text" aria-required="true"
                                                       class="sl-relative sl-w-full sl-h-md sl-text-base sl-pr-2.5 sl-pl-2.5 sl-rounded sl-border-transparent hover:sl-border-input focus:sl-border-primary sl-border sl-cursor-not-allowed"
                                                       value="" style="padding-left: 15px;"></div>
                                            <div><label role="button"
                                                        for="id_the_file_YxeQBmS1-file-input">Upload</label><input
                                                        type="file" hidden="" id="id_the_file_YxeQBmS1-file-input">
                                            </div>
                                        </div>
                                        <label aria-hidden="true" data-testid="param-label" for="id_nested_7pJPNXn2"
                                               class="sl-text-base">nested*</label><span class="sl-mx-3">:</span>
                                        <div>
                                            <div class="sl-flex sl-flex-1">
                                                <div class="sl-input sl-flex-1 sl-relative"><input
                                                            id="id_nested_7pJPNXn2" aria-label="nested"
                                                            placeholder="example: [[]]" type="text" aria-required="true"
                                                            class="sl-relative sl-w-full sl-h-md sl-text-base sl-pr-2.5 sl-pl-2.5 sl-rounded sl-border-transparent hover:sl-border-input focus:sl-border-primary sl-border"
                                                            value="[[]]"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="SendButtonHolder sl-mt-4 sl-p-4 sl-pt-0">
                                <div class="sl-stack sl-stack--horizontal sl-stack--2 sl-flex sl-flex-row sl-items-center">
                                    <button type="button"
                                            class="sl-button sl-h-sm sl-text-base sl-font-medium sl-px-1.5 sl-bg-primary hover:sl-bg-primary-dark active:sl-bg-primary-darker disabled:sl-bg-canvas-100 sl-text-on-primary disabled:sl-text-body sl-rounded sl-border-transparent sl-border disabled:sl-opacity-70">
                                        Send API Request
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if($metadata['example_languages'])
                    <div class="sl-panel sl-outline-none sl-w-full sl-rounded-lg">
                        <div class="sl-panel__titlebar sl-flex sl-items-center sl-relative focus:sl-z-10 sl-text-base sl-leading-none sl-pr-3 sl-pl-4 sl-bg-canvas-200 sl-text-body sl-border-input focus:sl-border-primary sl-select-none">
                            <div class="sl-flex sl-flex-1 sl-items-center sl-h-lg">
                                <div class="sl--ml-2">
                                    Request sample:
                                    <select class="example-request-lang-toggle" aria-label="Request Sample Language"
                                            onchange="switchExampleLanguage(event.target.value);">
                                        @foreach($metadata['example_languages'] as $language)
                                            <option>{{ $language }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        @foreach($metadata['example_languages'] as $index => $language)
                                <div class="example-request example-request-{{ $language }}"
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
                                            <div class="sl-mb-2 sl-inline-block">Response sample:</div>
                                            <div class="sl-mb-2 sl-inline-block"><select class="example-response-{{ $endpoint->endpointId() }}-toggle" aria-label="Response sample"
                                                    onchange="switchExampleResponse('{{ $endpoint->endpointId() }}', event.target.value);">
                                                    @foreach($endpoint->responses as $index => $response)
                                                    <option value="{{ $index }}">{{ $response->description ?: $response->status }}</option>
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
                                                        <small onclick="wasOpen = parentElement.parentElement.open; event.target.querySelector('.open').style.display = !wasOpen ? 'none' : 'initial';  event.target.querySelector('.close').style.display = wasOpen ? 'none' : 'initial'; ">
                                                            <svg focusable="false"style="display: none;" class="close svg-inline--fa fa-chevron-down fa-fw fa-sm sl-icon" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path fill="currentColor" d="M224 416c-8.188 0-16.38-3.125-22.62-9.375l-192-192c-12.5-12.5-12.5-32.75 0-45.25s32.75-12.5 45.25 0L224 338.8l169.4-169.4c12.5-12.5 32.75-12.5 45.25 0s12.5 32.75 0 45.25l-192 192C240.4 412.9 232.2 416 224 416z"></path></svg>
                                                            <svg focusable="false" class="open svg-inline--fa fa-chevron-right fa-fw fa-sm sl-icon" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path fill="currentColor" d="M96 480c-8.188 0-16.38-3.125-22.62-9.375c-12.5-12.5-12.5-32.75 0-45.25L242.8 256L73.38 86.63c-12.5-12.5-12.5-32.75 0-45.25s32.75-12.5 45.25 0l192 192c12.5 12.5 12.5 32.75 0 45.25l-192 192C112.4 476.9 104.2 480 96 480z"></path></svg>
                                                            Headers
                                                        </small>
                                                    </summary>
                                                    <pre><code class="language-http">@foreach($response->headers as $header => $value)
{{ $header }}: {{ is_array($value) ? implode('; ', $value) : $value }}
@endforeach </code></pre></details>@endif
                                        @if(is_string($response->content) && Str::startsWith($response->content, "<<binary>>"))
                                            <pre><code>[Binary data] - {{ htmlentities(str_replace("<<binary>>", "", $response->content)) }}</code></pre>
                                                @elseif($response->status == 204)
                                            <pre><code>[Empty response]</code></pre>
                                                @else
                                                    @php($parsed = json_decode($response->content))
                                                    {{-- If response is a JSON string, prettify it. Otherwise, just print it --}}
                                            <pre><code style="max-height: 300px;" class="language-json sl-overflow-x-auto sl-overflow-y-auto">{!! htmlentities($parsed != null ? json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $response->content) !!}</code></pre>
                                        @endif
                                    </div>
                                </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>


<span id="execution-results-{{ $endpoint->endpointId() }}" hidden>
    <blockquote>Received response<span
                id="execution-response-status-{{ $endpoint->endpointId() }}"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-{{ $endpoint->endpointId() }}"></code></pre>
</span>
<span id="execution-error-{{ $endpoint->endpointId() }}" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-{{ $endpoint->endpointId() }}"></code></pre>
</span>
<form id="form-{{ $endpoint->endpointId() }}" data-method="{{ $endpoint->httpMethods[0] }}"
      data-path="{{ $endpoint->uri }}"
      data-authed="{{ $endpoint->metadata->authenticated ? 1 : 0 }}"
      data-hasfiles="{{ $endpoint->hasFiles() ? 1 : 0 }}"
      data-isarraybody="{{ $endpoint->isArrayBody() ? 1 : 0 }}"
      data-headers='@json($endpoint->headers)'
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
    @if($endpoint->metadata->authenticated && $metadata['auth']['location'] === 'header')
        <p>
            <label id="auth-{{ $endpoint->endpointId() }}" hidden>{{ $metadata['auth']['name'] }} header:
                <b><code>{{ $metadata['auth']['prefix'] }}</code></b>
                <input type="text"
                       name="{{ $metadata['auth']['name'] }}"
                       data-prefix="{{ $metadata['auth']['prefix'] }}"
                       data-endpoint="{{ $endpoint->endpointId() }}"
                       data-component="header"></label>
        </p>
    @endif
</form>
