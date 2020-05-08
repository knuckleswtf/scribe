@component('scribe::components.badges.base', [
    'colour' => \Knuckles\Scribe\Tools\WritingUtils::$httpMethodToCssColour[$method],
    'text' => $method,
    ])
@endcomponent
