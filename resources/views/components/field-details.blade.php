@php
    $html ??= []; $class = $html['class'] ?? null;
@endphp
<b style="line-height: 2;"><code>{{ $name }}</code></b>&nbsp;&nbsp;
@if($type)<small>{{ $type }}</small>@endif&nbsp;
@if($isInput && !$required)<i>optional</i>@endif &nbsp;
@if($isInput && empty($hasChildren))
    @php
        $isList = Str::endsWith($type, '[]');
        $fullName = str_replace('[]', '.0', $fullName ?? $name);
        $baseType = $isList ? substr($type, 0, -2) : $type;
        // Ignore the first '[]': the frontend will take care of it
        while (\Str::endsWith($baseType, '[]')) {
            $fullName .= '.0';
            $baseType = substr($baseType, 0, -2);
        }
        // When the body is an array, the item names will be ".0.thing"
        $fullName = ltrim($fullName, '.');
        $inputType = match($baseType) {
            'number', 'integer' => 'number',
            'file' => 'file',
            default => 'text',
        };
    @endphp
    @if($type === 'boolean')
        <label data-endpoint="{{ $endpointId }}" style="display: none">
            <input type="radio" name="{{ $fullName }}"
                   value="{{$component === 'body' ? 'true' : 1}}"
                   data-endpoint="{{ $endpointId }}"
                   data-component="{{ $component }}" @if($class)class="{{ $class }}"@endif
            >
            <code>true</code>
        </label>
        <label data-endpoint="{{ $endpointId }}" style="display: none">
            <input type="radio" name="{{ $fullName }}"
                   value="{{$component === 'body' ? 'false' : 0}}"
                   data-endpoint="{{ $endpointId }}"
                   data-component="{{ $component }}" @if($class)class="{{ $class }}"@endif
            >
            <code>false</code>
        </label>
    @elseif($isList)
        <input type="{{ $inputType }}" style="display: none"
               @if($inputType === 'number')step="any"@endif
               name="{{ $fullName."[0]" }}" @if($class)class="{{ $class }}"@endif
               data-endpoint="{{ $endpointId }}"
               data-component="{{ $component }}">
        <input type="{{ $inputType }}" style="display: none"
               name="{{ $fullName."[1]" }}" @if($class)class="{{ $class }}"@endif
               data-endpoint="{{ $endpointId }}"
               data-component="{{ $component }}">
    @else
        <input type="{{ $inputType }}" style="display: none"
               @if($inputType === 'number')step="any"@endif
               name="{{ $fullName }}" @if($class)class="{{ $class }}"@endif
               data-endpoint="{{ $endpointId }}"
               value="{!! (isset($example) && (is_string($example) || is_numeric($example))) ? $example : '' !!}"
               data-component="{{ $component }}">
    @endif
@endif
<br>
@php
    if($example !== null && $example !== '' && !is_array($example)) {
        $exampleAsString = $example;
        if (is_bool($example)) {
            $exampleAsString = $example ? "true" : "false";
        }
        $description .= " Example: `$exampleAsString`";
    }
@endphp
{!! Parsedown::instance()->text(trim($description)) !!}
@if(!empty($enumValues))
Must be one of:
<ul style="list-style-type: square;">{!! implode(" ", array_map(fn($val) => "<li><code>$val</code></li>", $enumValues)) !!}</ul>
@endif
