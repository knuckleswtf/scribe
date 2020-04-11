@component('scribe::components.badges.base', [
    'colour' => \Knuckles\Scribe\Tools\Utils::$httpMethodToCssColour[$method],
    'text' => $method,
    ])
@endcomponent
