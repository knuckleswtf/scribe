<a href="#" id="nav-button">
    <span>
        MENU
        <img src="{!! $assetPathPrefix !!}images/navbar.png" alt="navbar-image" />
    </span>
</a>
<div class="tocify-wrapper">
    @if($metadata['logo'] != false)
        <img src="{{ $metadata['logo'] }}" alt="logo" class="logo" style="padding-top: 10px;" width="230px"/>
    @endif

    @isset($metadata['example_languages'])
        <div class="lang-selector">
            @foreach($metadata['example_languages'] as $lang)
                <a href="#" data-language-name="{{ $lang }}">{{ $lang }}</a>
            @endforeach
        </div>
    @endisset

    <div class="search">
        <input type="text" class="search" id="input-search" placeholder="Search">
    </div>

    <ul id="toc">
        @foreach($groupedEndpoints as $group)
            <ul id="tocify-header{{ $loop->index }}" class="tocify-header">
                <li class="tocify-item level-1" data-unique="{!! Str::slug($group['name']) !!}">
                    <a href="#{!! Str::slug($group['name']) !!}">{!! $group['name'] !!}</a>
                </li>
                @if (count($group['endpoints']) > 0)
                    <ul class="tocify-subheader" data-tag="{{ $loop->index }}">
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
    </ul>

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
