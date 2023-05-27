@php
    use Knuckles\Scribe\Tools\Utils as u;
@endphp
# {{ u::trans("scribe::headings.introduction") }}

{!! $description !!}

<aside>
    <strong>{{ u::trans("scribe::labels.base_url") }}</strong>: <code>{!! $baseUrl !!}</code>
</aside>

{!! $introText !!}

