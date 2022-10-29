@php
    use Knuckles\Scribe\Tools\WritingUtils as u;
@endphp
        <!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{!! $metadata['title'] !!}</title>

    <link href="https://fonts.googleapis.com/css?family=PT+Sans&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{!! $assetPathPrefix !!}css/theme-elements.style.css" media="screen">

    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.10/lodash.min.js"></script>

    <link rel="stylesheet"
          href="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/styles/docco.min.css">
    <script src="https://unpkg.com/@highlightjs/cdn-assets@10.7.2/highlight.min.js"></script>
    <script>hljs.highlightAll();</script>
    <style>
        .hljs {
            /* Remove highlightjs background color */
            background-color: transparent; !important;
        }
    </style>

    @if($metadata['example_languages'])
        <script>
            function switchExampleLanguage(lang) {
                document.querySelectorAll(`.example-request`).forEach(el => el.style.display = 'none');
                document.querySelectorAll(`.example-request-${lang}`).forEach(el => el.style.display = 'initial');
                document.querySelectorAll(`.example-request-lang-toggle`).forEach(el => el.value = lang);
            }
        </script>
    @endif
    <script>
        function switchExampleResponse(endpointId, index) {
            document.querySelectorAll(`.example-response-${endpointId}`).forEach(el => el.style.display = 'none');
            document.querySelectorAll(`.example-response-${endpointId}-${index}`).forEach(el => el.style.display = 'initial');
            document.querySelectorAll(`.example-response-${endpointId}-toggle`).forEach(el => el.value = index);
        }
    </script>

    @if($tryItOut['enabled'] ?? true)
        <script>
            var baseUrl = "{{ $tryItOut['base_url'] ?? config('app.url') }}";
            var useCsrf = Boolean({{ $tryItOut['use_csrf'] ?? null }});
            var csrfUrl = "{{ $tryItOut['csrf_url'] ?? null }}";
        </script>
        <script src="{{ u::getVersionedAsset($assetPathPrefix.'js/tryitout.js') }}"></script>
    @endif

    <script src="{{ u::getVersionedAsset($assetPathPrefix.'js/theme-elements.js') }}"></script>

</head>

<body data-languages="{{ json_encode($metadata['example_languages'] ?? []) }}">


<div style="height: 100%;">
    <div data-overlay-container="true" class="" style="height: 100%;">
        <div class="sl-elements sl-antialiased sl-h-full sl-text-base sl-font-ui sl-text-body">
            <div class="sl-elements-api sl-flex sl-inset-0 sl-h-full">

                @include("scribe::themes.elements.sidebar")

                <div class="sl-overflow-y-auto sl-flex-1 sl-w-full sl-px-24 sl-bg-canvas">
                    <div class="sl-py-16" style="max-width: 1500px;">

                        <div class="sl-mb-10">
                            <div class="sl-flex sl-justify-between sl-items-center">
                                <div class="sl-relative">
                                    <h1 class="sl-text-5xl sl-leading-tight sl-font-prose sl-font-semibold sl-mb-4 sl-text-heading">
                                        {!! $metadata['title'] !!}
                                    </h1>
                                </div>
                                @if($metadata['openapi_spec_url'] || $metadata['postman_collection_url'])
                                    <div>
                                        <button type="button" aria-label="Download" aria-haspopup="true"
                                                aria-expanded="false"
                                                class="sl-button sl-h-sm sl-text-base sl-font-medium sl-ml-2 sl-px-1.5 sl-bg-canvas hover:sl-bg-canvas-50 active:sl-bg-canvas-100 sl-rounded sl-border-button sl-border disabled:sl-opacity-60">
                                            Download
                                            <span class="sl-text-xs sl--mr-0.5 sl-ml-1">
                                                <svg aria-hidden="true" focusable="false" data-prefix="fas"
                                                     data-icon="chevron-down"
                                                     class="svg-inline--fa fa-chevron-down fa-fw sl-icon" role="img"
                                                     xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                                                    <path fill="currentColor"
                                                          d="M224 416c-8.188 0-16.38-3.125-22.62-9.375l-192-192c-12.5-12.5-12.5-32.75 0-45.25s32.75-12.5 45.25 0L224 338.8l169.4-169.4c12.5-12.5 32.75-12.5 45.25 0s12.5 32.75 0 45.25l-192 192C240.4 412.9 232.2 416 224 416z"></path>
                                                </svg>
                                            </span>
                                        </button>

                                        <div class="sl-bg-transparent"><span hidden=""></span>
                                            <div style="border: 0px; clip: rect(0px, 0px, 0px, 0px); height: 1px; margin: -1px; overflow: hidden; padding: 0px; position: absolute; width: 1px; white-space: nowrap;">
                                                <button tabindex="-1" aria-label="Dismiss"></button>
                                            </div>
                                            <div data-ismodal="false" tabindex="-1"
                                                 data-testid="popover" data-ispopover="true"
                                                 class="sl-popover sl-inline-flex" role="presentation"
                                                 style="position: absolute; z-index: 100000; left: 502.045px; top: 158px; max-height: 359.091px;">
                                                <div class="sl-menu sl-menu--pointer-interactions sl-inline-block sl-overflow-y-auto sl-w-full sl-py-2 sl-bg-canvas-pure sl-cursor sl-no-focus-ring"
                                                     role="menu" style="min-width: 150px; max-width: 400px;">
                                                    @if($metadata['postman_collection_url'])
                                                        <div class="sl-menu-item sl-flex sl-items-center sl-text-base sl-whitespace-nowrap sl-pt-1 sl-pr-3 sl-pb-1 sl-pl-3"
                                                             role="menuitem">
                                                            <div class="sl-menu-item__title-wrapper sl-flex-1 sl-w-full sl-pr-0">
                                                                <div class="sl-truncate">
                                                                    <a href="{!! $metadata['postman_collection_url'] !!}" target="_blank">Postman
                                                                        collection</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                    @if($metadata['openapi_spec_url'])
                                                        <div class="sl-menu-item sl-flex sl-items-center sl-text-base sl-whitespace-nowrap sl-pt-1 sl-pr-3 sl-pb-1 sl-pl-3"
                                                             role="menuitem">
                                                            <div class="sl-menu-item__title-wrapper sl-flex-1 sl-w-full sl-pr-0">
                                                                <div class="sl-truncate">
                                                                    <a href="{!! $metadata['openapi_spec_url'] !!}" target="_blank">OpenAPI
                                                                        spec</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="sl-popover__tip sl-absolute sl-text-canvas-pure"
                                                     style="top: -10px; font-size: 16px; line-height: 0; margin-left: -5px; left: 117.888px;">
                                                    <svg aria-hidden="true" focusable="false" data-prefix="fas"
                                                         data-icon="caret-up" class="svg-inline--fa fa-caret-up sl-icon"
                                                         role="img" xmlns="http://www.w3.org/2000/svg"
                                                         viewBox="0 0 320 512">
                                                        <path fill="currentColor"
                                                              d="M9.39 265.4l127.1-128C143.6 131.1 151.8 128 160 128s16.38 3.125 22.63 9.375l127.1 128c9.156 9.156 11.9 22.91 6.943 34.88S300.9 320 287.1 320H32.01c-12.94 0-24.62-7.781-29.58-19.75S.2333 274.5 9.39 265.4z"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                            <div style="border: 0px; clip: rect(0px, 0px, 0px, 0px); height: 1px; margin: -1px; overflow: hidden; padding: 0px; position: absolute; width: 1px; white-space: nowrap;">
                                                <button tabindex="-1" aria-label="Dismiss"></button>
                                            </div>
                                            <span hidden=""></span>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="sl-relative">
                                <div class="sl-prose sl-markdown-viewer sl-my-5">
                                    {!! $intro !!}

                                    {!! $auth !!}
                                </div>
                            </div>
                        </div>

                        @include("scribe::themes.elements.groups")

                        <div class="sl-mb-10">
                            <div class="sl-relative">
                                <div class="sl-prose sl-markdown-viewer sl-my-5">
                        {!! $append !!}
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </div>
</div>
</body>
</html>
