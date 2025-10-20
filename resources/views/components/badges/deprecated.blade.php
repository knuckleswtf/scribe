@if($deprecated !== false)
@php($text = $deprecated === true ? 'deprecated' : "deprecated:$deprecated")
@component('scribe::components.badges.base', ['colour' => 'darkgoldenrod', 'text' => $text])
@endcomponent
@endif
