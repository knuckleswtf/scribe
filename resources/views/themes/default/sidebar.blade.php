<a href="#" id="nav-button">
    <span>
        MENU
        <img src="{!! $assetPathPrefix !!}images/navbar.png" alt="navbar-image" />
    </span>
</a>
<div class="tocify-wrapper">
    @if($metadata['logo'] != false)
        <img src="{{ $metadata['logo'] }}" alt="logo" class="logo" style="padding-top: 10px;" width="100%"/>
    @endif

    @isset($metadata['example_languages'])
        <div class="lang-selector">
            @foreach($metadata['example_languages'] as $name => $lang)
                @php if (is_numeric($name)) $name = $lang; @endphp
                <button type="button" class="lang-button" data-language-name="{{ $lang }}">{{ $name }}</button>
            @endforeach
        </div>
    @endisset

    <div class="search">
        <input type="text" class="search" id="input-search" placeholder="Search">
    </div>

    <div id="toc">
        @php
            $previousH1 = null;
            $inSubHeading = false;
            $headingsCount = 0;
        @endphp
        @foreach($headingsBeforeEndpoints as $heading)
            @if($heading['level'] === 1)
                @if($previousH1)
                    </ul>
                @endif
                @if($inSubHeading)
                    @php($inSubHeading = false)
                    </ul>
                @endif
                <ul id="tocify-header-{{ $headingsCount }}" class="tocify-header">
                    <li class="tocify-item level-1" data-unique="{!! $heading['slug'] !!}">
                        <a href="#{!! $heading['slug'] !!}">{!! $heading['text'] !!}</a>
                    </li>
                @php($previousH1 = $heading)
                @php($headingsCount += 1)
            @elseif ($heading['level'] === 2 && $previousH1)
                @if(!$inSubHeading)
                    <ul id="tocify-subheader-{!! $previousH1['slug'] !!}" class="tocify-subheader">
                    @php($inSubHeading = true)
                @endif
                    <li class="tocify-item level-2"
                        data-unique="{!! $previousH1['slug'] !!}-{!! $heading['slug'] !!}">
                        <a href="#{!! $heading['slug'] !!}">{{ $heading['text'] }}</a>
                    </li>
            @endif

            @if($loop->last)
                    @if($inSubHeading)
                    </ul>
                    @endif
                </ul>
            @endif
        @endforeach

        @foreach($groupedEndpoints as $group)
            <ul id="tocify-header-{{ $loop->index + $headingsCount }}" class="tocify-header">
                <li class="tocify-item level-1" data-unique="{!! Str::slug($group['name']) !!}">
                    <a href="#{!! Str::slug($group['name']) !!}">{!! $group['name'] !!}</a>
                </li>
                @if (count($group['endpoints']) > 0)
                    <ul id="tocify-subheader-{!! Str::slug($group['name']) !!}" class="tocify-subheader">
                @endif
                @foreach($group['endpoints'] as $endpoint)
                    <li class="tocify-item level-2" data-unique="{!! Str::slug($group['name']) !!}-{!! $endpoint->endpointId() !!}">
                        <a href="#{!! Str::slug($group['name']) !!}-{!! $endpoint->endpointId() !!}">{{ $endpoint->metadata->title ?: ($endpoint->httpMethods[0]." ".$endpoint->uri)}}</a>
                    </li>
                @endforeach
                @if (count($group['endpoints']) > 0)
                    </ul>
                @endif
            </ul>
        @endforeach

        @php($previousH1 = null)
        @php($inSubHeading = false)
        @php($headingsCount += count($groupedEndpoints))

        @foreach($headingsAfterEndpoints as $heading)
            @if($heading['level'] === 1)
                @if($previousH1)
                    </ul>
                @endif
                @if($inSubHeading)
                    @php($inSubHeading = false)
                    </ul>
                @endif
                <ul id="tocify-header-{{ $headingsCount }}" class="tocify-header">
                    <li class="tocify-item level-1" data-unique="{!! $heading['slug'] !!}">
                        <a href="#{!! $heading['slug'] !!}">{!! $heading['text'] !!}</a>
                    </li>
                @php($previousH1 = $heading)
                @php($headingsCount += 1)
            @elseif ($heading['level'] === 2 && $previousH1)
                @if(!$inSubHeading)
                    <ul id="tocify-subheader-{!! $previousH1['slug'] !!}" class="tocify-subheader">
                    @php($inSubHeading = true)
                @endif
                    <li class="tocify-item level-2"
                        data-unique="{!! $previousH1['slug'] !!}-{!! $heading['slug'] !!}">
                        <a href="#{!! $heading['slug'] !!}">{{ $heading['text'] }}</a>
                    </li>
            @endif

            @if($loop->last)
                    @if($inSubHeading)
                    </ul>
                    @endif
                </ul>
            @endif
        @endforeach
    </div>

    @if(isset($metadata['links']))
        <ul class="toc-footer" id="toc-footer">
            @foreach($metadata['links'] as $link)
                <li>{!! $link !!}</li>
            @endforeach
        </ul>
    @endif
    <ul class="toc-footer" id="last-updated">
        <li>Last updated: {{ $metadata['last_updated'] }}</li>
    </ul>
</div>
