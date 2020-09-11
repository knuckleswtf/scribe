<b><code>{{ $name }}</code></b>&nbsp;&nbsp;@if($type)<small>{{ $type }}</small>@endif @if(!$required)
    <i>optional</i>@endif &nbsp;
@if(($isInput ?? true) && empty($hasChildren))
@php
    $isList = Str::endsWith($type, '[]');
    $fullName = empty($parent) ? $name : "$parent.$name";
    $baseType = $isList ? substr($type, 0, -2) : $type;
    switch($baseType) {
        case 'number':
        case 'integer':
            $inputType = 'number';
            break;
        case 'file':
            $inputType = 'file';
            break;
        default:
            $inputType = 'text';
    }
@endphp
@if($type === 'boolean')
<label><input type="radio" name="{{ $fullName }}" value="true" data-endpoint="{{ $endpointId }}" data-component="{{ $component }}" @if($required)required @endif hidden><code>true</code></label>
<label><input type="radio" name="{{ $fullName }}" value="false" data-endpoint="{{ $endpointId }}" data-component="{{ $component }}" @if($required)required @endif hidden><code>false</code></label>
@elseif($isList)
<input type="{{ $inputType }}" name="{{ $fullName.".0" }}" data-endpoint="{{ $endpointId }}" data-component="{{ $component }}" @if($required)required @endif hidden>
<input type="{{ $inputType }}" name="{{ $fullName.".1" }}" data-endpoint="{{ $endpointId }}" data-component="{{ $component }}" @if($required)required @endif hidden>
@else
<input type="{{ $inputType }}" name="{{ $fullName }}" data-endpoint="{{ $endpointId }}" data-component="{{ $component }}" @if($required)required @endif hidden>
@endif
@endif
<br>
{!! $description !!}