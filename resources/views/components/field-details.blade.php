<b><code>{{ $name }}</code></b>&nbsp;&nbsp;@if($type)<small>{{ $type }}</small>@endif @if(!$required)
    <i>optional</i>@endif &nbsp;
@if(($isInput ?? true) && empty($hasChildren))
@php
    $isList = Str::endsWith($type, '[]');
    $isPassword = preg_match('/password/', $name);
    $fullName = str_replace('[]', '.0', $name);
    $baseType = $isList ? substr($type, 0, -2) : $type;
    // Ignore the first '[]': the frontend will take care of it
    while (\Str::endsWith($baseType, '[]')) {
        $fullName .= '.0';
        $baseType = substr($baseType, 0, -2);
    }
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
<label data-endpoint="{{ $endpointId }}" hidden><input type="radio" name="{{ $fullName }}" value="{{$component === 'body' ? 'true' : 1}}" data-endpoint="{{ $endpointId }}" data-component="{{ $component }}" @if($required)required @endif><code>true</code></label>
<label data-endpoint="{{ $endpointId }}" hidden><input type="radio" name="{{ $fullName }}" value="{{$component === 'body' ? 'false' : 0}}" data-endpoint="{{ $endpointId }}" data-component="{{ $component }}" @if($required)required @endif><code>false</code></label>
@elseif($isList)
<input type="{{ $isPassword ? 'password' : $inputType }}" name="{{ $fullName.".0" }}" data-endpoint="{{ $endpointId }}" data-component="{{ $component }}" @if($required)required @endif hidden>
<input type="{{ $isPassword ? 'password' : $inputType }}" name="{{ $fullName.".1" }}" data-endpoint="{{ $endpointId }}" data-component="{{ $component }}" hidden>
@else
<input type="{{ $isPassword ? 'password' : $inputType }}" name="{{ $fullName }}" data-endpoint="{{ $endpointId }}" data-component="{{ $component }}" @if($required)required @endif hidden>
@endif
@endif
<br>
{!! $description !!}
