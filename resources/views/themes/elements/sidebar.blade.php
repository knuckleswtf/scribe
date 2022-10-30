<div class="sl-flex sl-overflow-y-auto sl-flex-col sl-sticky sl-inset-y-0 sl-pt-8 sl-bg-canvas-100 sl-border-r"
     style="width: calc((100% - 1800px) / 2 + 300px); padding-left: calc((100% - 1800px) / 2); min-width: 300px; max-height: 100vh">
    <div class="sl-flex sl-items-center sl-mb-5 sl-ml-4">
        @if($metadata['logo'] != false)
            <div class="sl-inline sl-overflow-x-hidden sl-overflow-y-hidden sl-mr-3 sl-rounded-lg"
                 style="background-color: transparent;">
                <img src="{{ $metadata['logo'] }}" height="30px" width="30px" alt="logo">
            </div>
        @endif
        <h4 class="sl-text-paragraph sl-leading-snug sl-font-prose sl-font-semibold sl-text-heading">
            {{ $metadata['title'] }}
        </h4>
    </div>

    <div class="sl-flex sl-overflow-y-auto sl-flex-col sl-flex-grow sl-flex-shrink">
        <div class="sl-overflow-y-auto sl-w-full sl-bg-canvas-100">
            <div class="sl-my-3">
                @foreach($headings as $h1)
                    <div title="{!! $h1['name'] !!}"
                         class="sl-flex sl-items-center sl-h-md sl-pr-4 sl-pl-4 sl-bg-canvas-100 hover:sl-bg-canvas-200 sl-cursor-pointer sl-select-none">
                        <a href="#{!! $h1['slug'] !!}" class="sl-flex-1 sl-items-center sl-truncate sl-mr-1.5 sl-p-0" onclick="toggleSidebarChildren(event.target.parentElement, '{{ $h1['slug'] }}')">{!! $h1['name'] !!}</a>
                        @if(count($h1['subheadings']) > 0)
                            <div class="sl-flex sl-items-center sl-text-xs">
                                <div class="sl-flex sl-items-center">
                                    <svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="chevron-right" class="expand-chevron svg-inline--fa fa-chevron-right fa-fw sl-icon sl-text-muted" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path fill="currentColor" d="M96 480c-8.188 0-16.38-3.125-22.62-9.375c-12.5-12.5-12.5-32.75 0-45.25L242.8 256L73.38 86.63c-12.5-12.5-12.5-32.75 0-45.25s32.75-12.5 45.25 0l192 192c12.5 12.5 12.5 32.75 0 45.25l-192 192C112.4 476.9 104.2 480 96 480z"></path></svg>
                                    <svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="chevron-down" style="display: none;"
                                         class="expanded-chevron svg-inline--fa fa-chevron-down fa-fw sl-icon sl-text-muted"
                                         xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                                        <path fill="currentColor"
                                              d="M224 416c-8.188 0-16.38-3.125-22.62-9.375l-192-192c-12.5-12.5-12.5-32.75 0-45.25s32.75-12.5 45.25 0L224 338.8l169.4-169.4c12.5-12.5 32.75-12.5 45.25 0s12.5 32.75 0 45.25l-192 192C240.4 412.9 232.2 416 224 416z"></path>
                                    </svg>
                                </div>
                            </div>
                        @endif
                    </div>

                    @foreach($h1['subheadings'] as $h2)
                        <div class="children-{{ $h1['slug'] }} sl-flex sl-items-center sl-h-md sl-pr-4 sl-pl-8 sl-bg-canvas-100 hover:sl-bg-canvas-200 sl-cursor-pointer sl-select-none"
                                style="display: none;"
                        >
                            <div class="sl-flex-1 sl-items-center sl-truncate sl-mr-1.5 sl-p-0" id="sl-toc-{!! $h2['slug'] !!}" title="{!! $h2['slug'] !!}">
                                    <a class="ElementsTableOfContentsItem sl-block sl-no-underline" href="#{!! $h2['slug'] !!}" onclick="toggleSidebarChildren(event.target.parentElement.parentElement, '{{ $h2['slug'] }}')">
                                    {!! $h2['name'] !!}
                                    </a>
                            </div>
                            @if(count($h2['subheadings']) > 0)
                                <div class="sl-flex sl-items-center sl-text-xs">
                                    <div class="sl-flex sl-items-center">
                                        <svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="chevron-right" class="expand-chevron svg-inline--fa fa-chevron-right fa-fw sl-icon sl-text-muted" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path fill="currentColor" d="M96 480c-8.188 0-16.38-3.125-22.62-9.375c-12.5-12.5-12.5-32.75 0-45.25L242.8 256L73.38 86.63c-12.5-12.5-12.5-32.75 0-45.25s32.75-12.5 45.25 0l192 192c12.5 12.5 12.5 32.75 0 45.25l-192 192C112.4 476.9 104.2 480 96 480z"></path></svg>
                                        <svg aria-hidden="true" focusable="false" data-prefix="fas"
                                             data-icon="chevron-down"
                                             class="expanded-chevron svg-inline--fa fa-chevron-down fa-fw sl-icon sl-text-muted"
                                             style="display: none;"
                                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                                            <path fill="currentColor"
                                                  d="M224 416c-8.188 0-16.38-3.125-22.62-9.375l-192-192c-12.5-12.5-12.5-32.75 0-45.25s32.75-12.5 45.25 0L224 338.8l169.4-169.4c12.5-12.5 32.75-12.5 45.25 0s12.5 32.75 0 45.25l-192 192C240.4 412.9 232.2 416 224 416z"></path>
                                        </svg>
                                    </div>
                                </div>
                            @endif
                        </div>

                        @foreach($h2['subheadings'] as $h3)
                            <a class="children-{{ $h2['slug'] }} ElementsTableOfContentsItem sl-block sl-no-underline"
                               href="#{!! $h3['slug'] !!}"
                               style="display: none;">
                                <div id="sl-toc-{!! $h3['slug'] !!}" title="{!! $h3['slug'] !!}"
                                     class="sl-flex sl-items-center sl-h-md sl-pr-4 sl-pl-12 sl-bg-canvas-100 hover:sl-bg-canvas-200 sl-cursor-pointer sl-select-none">
                                    <div class="sl-flex-1 sl-items-center sl-truncate sl-mr-1.5">{!! $h3['name'] !!}</div>
                                </div>
                            </a>
                        @endforeach
                    @endforeach
                @endforeach
            </div>

        </div>
        <div class="sl-flex sl-items-center sl-px-4 sl-py-3 sl-border-t">
            {{ $metadata['last_updated'] }}
        </div>

        <div class="sl-flex sl-items-center sl-px-4 sl-py-3 sl-border-t">
            <a href="http://github.com/knuckleswtf/scribe">Documentation powered by Scribe ✍</a>
        </div>
    </div>
</div>
