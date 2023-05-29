@php
    use Knuckles\Scribe\Tools\Utils as u;
@endphp
# {{ u::trans("scribe::headings.auth") }}

@if(!$isAuthed)
{!! u::trans("scribe::auth.none") !!}
@else
{!! $authDescription !!}

{!! $extraAuthInfo !!}
@endif
