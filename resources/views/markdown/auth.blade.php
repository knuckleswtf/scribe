@php
    use Knuckles\Scribe\Tools\Utils as u;
@endphp
# {{ u::trans("scribe::headers.auth") }}

@if(!$isAuthed)
{!! u::trans("scribe::no_auth") !!}
@else
{!! $authDescription !!}

{!! $extraAuthInfo !!}
@endif
