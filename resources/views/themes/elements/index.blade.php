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
            background-color: transparent;
        !important;
        }
    </style>


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

<body>

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

    function toggleExpansionChevrons(evt) {
        let elem = evt.currentTarget;

        const newState = elem.querySelector('.expand-chevron').style.display === 'none' ? 'expand' : 'expanded';
        if (newState === 'expanded') {
            elem.querySelector('.expand-chevron').style.display = 'none';
            elem.querySelector('.expanded-chevron').style.removeProperty('display');
        } else {
            elem.querySelector('.expand-chevron').style.removeProperty('display')
            elem.querySelector('.expanded-chevron').style.display = 'none'
        }
    }

    function toggleElementChildren(evt) {
        let elem = evt.currentTarget;
        let children = elem.querySelector(`.children`);
        if (!children) return;

        if (children.contains(event.target)) return;

        let oldState = children.style.display
        if (oldState === 'none') {
            children.style.removeProperty('display');
            toggleExpansionChevrons(evt);
        } else {
            children.style.display = 'none';
            toggleExpansionChevrons(evt);
        }

        evt.stopPropagation();
    }

    function highlightSidebarItem(evt = null) {
        if (evt && evt.oldURL) {
            let oldHash = new URL(evt.oldURL).hash.slice(1);
            if (oldHash) {
                let previousItem = window['sidebar'].querySelector(`#toc-item-${oldHash}`);
                previousItem.classList.remove('sl-bg-primary-tint');
                previousItem.classList.add('sl-bg-canvas-100');
            }
        }

        let newHash = location.hash.slice(1);
        if (newHash) {
            let item = window['sidebar'].querySelector(`#toc-item-${newHash}`);
            item.classList.remove('sl-bg-canvas-100');
            item.classList.add('sl-bg-primary-tint');
        }
    }

    window.addEventListener('DOMContentLoaded', () => {
        highlightSidebarItem();

        document.querySelectorAll('.expandable').forEach(el => {
            el.addEventListener('click', toggleElementChildren);
        });

        document.querySelectorAll('details').forEach(el => {
            el.addEventListener('toggle', toggleExpansionChevrons);
        });
    });

    addEventListener('hashchange', highlightSidebarItem);
</script>

<div style="height: 100%;">
    <div data-overlay-container="true" class="" style="height: 100%;">
        <div class="sl-elements sl-antialiased sl-h-full sl-text-base sl-font-ui sl-text-body">
            <div class="sl-elements-api sl-flex sl-inset-0 sl-h-full">

                @include("scribe::themes.elements.sidebar")

                <div class="sl-overflow-y-auto sl-flex-1 sl-w-full sl-px-16 sl-bg-canvas">
                    <div class="sl-py-16" style="max-width: 1500px;">

                        <div class="sl-mb-10">
                            <div class="sl-mb-4">
                                <h1 class="sl-text-5xl sl-leading-tight sl-font-prose sl-font-semibold sl-text-heading">
                                    {!! $metadata['title'] !!}
                                </h1>
                                @if($metadata['postman_collection_url'])
                                    <a title="Download Postman collection" class="sl-mx-1"
                                       href="{!! $metadata['postman_collection_url'] !!}" target="_blank">
                                        <small>Postman collection →</small>
                                    </a>
                                @endif
                                @if($metadata['openapi_spec_url'])
                                    <a title="Download OpenAPI spec" class="sl-mx-1"
                                       href="{!! $metadata['openapi_spec_url'] !!}" target="_blank">
                                        <small>OpenAPI spec →</small>
                                    </a>
                                @endif
                            </div>

                            <div class="sl-prose sl-markdown-viewer sl-my-5">
                                {!! $intro !!}

                                {!! $auth !!}
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
