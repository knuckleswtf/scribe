@php
    use Knuckles\Scribe\Tools\Utils as u;
@endphp
# {{ u::trans("scribe::headers.introduction") }}

{!! $description !!}

<aside>
    <strong>{{ u::trans("scribe::base_url") }}</strong>: <code>{!! $baseUrl !!}</code>
</aside>

{!! $introText !!}

