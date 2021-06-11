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

    <link rel="stylesheet" href="{!! $assetPathPrefix !!}css/theme-default.style.css" media="screen">
    <link rel="stylesheet" href="{!! $assetPathPrefix !!}css/theme-default.print.css" media="print">
    <script src="{{ u::getVersionedAsset($assetPathPrefix.'js/theme-default.js') }}"></script>

    <link rel="stylesheet"
          href="//unpkg.com/@highlightjs/cdn-assets@10.7.2/styles/obsidian.min.css">
    <script src="//unpkg.com/@highlightjs/cdn-assets@10.7.2/highlight.min.js"></script>
    <script>hljs.highlightAll();</script>

@if($tryItOut['enabled'] ?? true)
    <script src="//cdn.jsdelivr.net/npm/lodash@4.17.10/lodash.min.js"></script>
    <script>
        var baseUrl = "{{ $tryItOut['base_url'] ?? config('app.url') }}";
    </script>
    <script src="{{ u::getVersionedAsset($assetPathPrefix.'js/tryitout.js') }}"></script>
@endif

</head>

<body class="" data-languages="{{ json_encode($metadata['example_languages'] ?? []) }}">
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
    <ul class="search-results"></ul>

    <ul id="toc">
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
<div class="page-wrapper">
    <div class="dark-box"></div>
    <div class="content">
        {!! $intro !!}

        {!! $auth !!}

        @include("scribe::themes.default.groups")

        {!! $append !!}
    </div>
    <div class="dark-box">
        @if(isset($metadata['example_languages']))
            <div class="lang-selector">
                @foreach($metadata['example_languages'] as $lang)
                    <a href="#" data-language-name="{{$lang}}">{{$lang}}</a>
                @endforeach
            </div>
        @endif
    </div>
</div>
@isset($metadata['example_languages'])
<script>
    $(function () {
        var exampleLanguages = @json($metadata['example_languages']);
        setupLanguages(exampleLanguages);
    });
</script>
@endisset
</body>
</html>