<b><code>{{ $name }}</code></b>&nbsp;&nbsp;@if($type)<small>{{ $type }}</small>@endif @if(!$required)
    <i>optional</i>@endif &nbsp;
@if(($isInput ?? true) && empty($hasChildren))
    @php
        $isList = Str::endsWith($type, '[]');
        $fullName =str_replace('[]', '.0', $name);
        $baseType = $isList ? substr($type, 0, -2) : $type;
        // Ignore the first '[]': the frontend will take care of it
        while (\Str::endsWith($baseType, '[]')) {
            $fullName .= '.0';
            $baseType = substr($baseType, 0, -2);
        }
        // When the body is an array, the item names will be ".0.thing"
        $fullName = ltrim($fullName, '.');
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
        <label data-endpoint="{{ $endpointId }}" hidden>
            <input type="radio" name="{{ $fullName }}"
                   value="{{$component === 'body' ? 'true' : 1}}"
                   data-endpoint="{{ $endpointId }}"
                   data-component="{{ $component }}"
            >
            <code>true</code>
        </label>
        <label data-endpoint="{{ $endpointId }}" hidden>
            <input type="radio" name="{{ $fullName }}"
                   value="{{$component === 'body' ? 'false' : 0}}"
                   data-endpoint="{{ $endpointId }}"
                   data-component="{{ $component }}"
            >
            <code>false</code>
        </label>
    @elseif($isList)
        <input type="{{ $inputType }}"
               name="{{ $fullName."[0]" }}"
               data-endpoint="{{ $endpointId }}"
               data-component="{{ $component }}" hidden>
        <input type="{{ $inputType }}"
               name="{{ $fullName."[1]" }}"
               data-endpoint="{{ $endpointId }}"
               data-component="{{ $component }}" hidden>
    @else
        <input type="{{ $inputType }}"
               name="{{ $fullName }}"
               data-endpoint="{{ $endpointId }}"
               value="{!! (isset($example) && (is_string($example) || is_numeric($example))) ? $example : '' !!}"
               data-component="{{ $component }}" hidden>
    @endif
@endif
<br>
{!! Parsedown::instance()->text($description) !!}
