@if($authenticated)@component('scribe::components.badges.base', ['colour' => "darkred", 'text' => 'requires authentication'])
@endcomponent
@endif
