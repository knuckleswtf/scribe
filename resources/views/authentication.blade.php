# Authenticating requests

@if(!$isAuthed)
This API is not authenticated.
@else
{!! $text !!}
@endif
