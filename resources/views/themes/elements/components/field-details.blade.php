@php
    $hasChildren ??= false;
    $isArrayBody = $name == "[]";
    $expandable = $hasChildren && !$isArrayBody;
@endphp

<div class="sl-flex sl-relative sl-max-w-full sl-py-2 sl-pl-3">
    <div class="sl-w-1 sl-mt-2 sl-mr-3 sl--ml-3 sl-border-t"></div>
    <div class="sl-stack sl-stack--vertical sl-stack--1 sl-flex sl-flex-1 sl-flex-col sl-items-stretch sl-max-w-full sl-ml-2 @if($expandable) sl-cursor-pointer @endif">
        <div class="sl-flex sl-items-center sl-max-w-full">
            @if($expandable)
                <div class="sl-flex sl-justify-center sl-w-8 sl--ml-8 sl-pl-3 sl-text-muted expansion-chevrons" role="button">
                    <svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="chevron-right"
                         class="svg-inline--fa fa-chevron-right fa-fw fa-sm sl-icon" role="img"
                         xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512">
                        <path fill="currentColor"
                              d="M96 480c-8.188 0-16.38-3.125-22.62-9.375c-12.5-12.5-12.5-32.75 0-45.25L242.8 256L73.38 86.63c-12.5-12.5-12.5-32.75 0-45.25s32.75-12.5 45.25 0l192 192c12.5 12.5 12.5 32.75 0 45.25l-192 192C112.4 476.9 104.2 480 96 480z"></path>
                    </svg>
                </div>
            @endif
            @unless($isArrayBody)
                <div class="sl-flex sl-items-baseline sl-text-base">
                    <div class="sl-font-mono sl-font-semibold sl-mr-2">{{ $name }}</div>
                    @if($type)
                        <span class="sl-truncate sl-text-muted">{{ $type }}</span>
                    @endif
                </div>
                @if($required)
                    <div class="sl-flex-1 sl-h-px sl-mx-3"></div>
                    <span class="sl-ml-2 sl-text-warning">required</span>
                @endif
            @endunless
        </div>
        @if($description)
        <div class="sl-prose sl-markdown-viewer" style="font-size: 12px;">
            {!! Parsedown::instance()->text($description) !!}
        </div>
        @endif
        @if(!empty($enumValues))
            Must be one of:
            <ul style="list-style-position: inside; list-style-type: square;">{!! implode(" ", array_map(fn($val) => "<li><code>$val</code></li>", $enumValues)) !!}</ul>
        @endif
        @if($isArrayBody)
            <div class="sl-flex sl-items-baseline sl-text-base">
                <div class="sl-font-mono sl-font-semibold sl-mr-2">array of:</div>
                @if($required)
                    <div class="sl-flex-1 sl-h-px sl-mx-3"></div>
                    <span class="sl-ml-2 sl-text-warning">required</span>
                @endif
            </div>
        @endif
        @if(!$hasChildren && !is_null($example) && $example != '')
            <div class="sl-stack sl-stack--horizontal sl-stack--2 sl-flex sl-flex-row sl-items-baseline sl-text-muted">
                <span>Example:</span> <!-- <span> important for spacing -->
                <div class="sl-flex sl-flex-1 sl-flex-wrap" style="gap: 4px;">
                    <div class="sl-max-w-full sl-break-all sl-px-1 sl-bg-canvas-tint sl-text-muted sl-rounded sl-border">
                        {{ is_array($example) ? json_encode($example) : $example }}
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
