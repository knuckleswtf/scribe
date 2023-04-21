# {{ __("scribe::headers.auth") }}

@if(!$isAuthed)
{!! __("scribe::no_auth") !!}
@else
{!! $authDescription !!}

{!! $extraAuthInfo !!}
@endif
