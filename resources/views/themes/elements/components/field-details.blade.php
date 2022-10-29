@php
    $hasChildren ??= false
@endphp

<div class="sl-flex sl-relative sl-max-w-full sl-py-2 sl-pl-3">
    <div class="sl-w-1 sl-mt-2 sl-mr-3 sl--ml-3 sl-border-t"></div>
    <div class="sl-stack sl-stack--vertical sl-stack--1 sl-flex sl-flex-1 sl-flex-col sl-items-stretch sl-max-w-full sl-ml-2">
        <div class="sl-flex sl-items-center sl-max-w-full @if($hasChildren) sl-cursor-pointer @endif">
            @if($hasChildren)
                <div class="sl-flex sl-justify-center sl-w-8 sl--ml-8 sl-pl-3 sl-text-muted"
                     role="button">
                    <svg aria-hidden="true" focusable="false" data-prefix="fas"
                         data-icon="chevron-down"
                         class="svg-inline--fa fa-chevron-down fa-fw fa-sm sl-icon"
                         role="img" xmlns="http://www.w3.org/2000/svg"
                         viewBox="0 0 448 512">
                        <path fill="currentColor"
                              d="M224 416c-8.188 0-16.38-3.125-22.62-9.375l-192-192c-12.5-12.5-12.5-32.75 0-45.25s32.75-12.5 45.25 0L224 338.8l169.4-169.4c12.5-12.5 32.75-12.5 45.25 0s12.5 32.75 0 45.25l-192 192C240.4 412.9 232.2 416 224 416z"></path>
                    </svg>
                </div>
            @endif
            <div class="sl-flex sl-items-baseline sl-text-base">
                <div class="sl-font-mono sl-font-semibold sl-mr-2">{{ $name }}</div>
                @if($type)
                    <span class="sl-truncate sl-text-muted">{{ $type }}</span>
                @endif
            </div>
            <div class="sl-flex-1 sl-h-px sl-mx-3"></div>
            @if($required)
                <span class="sl-ml-2 sl-text-warning">required</span>
            @endif
        </div>
        <div class="sl-prose sl-markdown-viewer" style="font-size: 12px;">
            <p>
                {!! Parsedown::instance()->text($description) !!}
            </p>
        </div>
        @if(!$hasChildren && !is_null($example) && $example != '')
            <div class="sl-stack sl-stack--horizontal sl-stack--2 sl-flex sl-flex-row sl-items-baseline sl-text-muted">
                <span>Example:</span>
                <div class="sl-flex sl-flex-1 sl-flex-wrap" style="gap: 4px;">
                    <span class="sl-max-w-full sl-break-all sl-px-1 sl-bg-canvas-tint sl-text-muted sl-rounded sl-border">
                        {{ is_array($example) ? json_encode($example) : $example }}
                    </span>
                </div>
            </div>
        @endif
    </div>
</div>
