
@foreach($parameters as $name => $parameter)
@if(!empty($parameter['fields']))
<p>
<details>
<summary>
@component('scribe::components.field-details', [
  'name' => $name,
  'type' => $parameter['type'] ?? 'string',
  'required' => $parameter['required'] ?? false,
  'description' => $parameter['description'] ?? '',
  'endpointId' => $endpointId,
  'hasChildren' => true,
  'component' => 'body',
])
@endcomponent
</summary>
<br>
@foreach($parameter['fields'] as $subfieldName => $subfield)
@php
    // Set the parent name for the field properly.
   // This allows the input fields used by tryItOut to have the correct dot path
   // which we pass to lodash set to set the values in our request body
    $arrayAccessors = '';
    $type = $parameter['type'];
    while (\Str::endsWith($type, '[]')) {
        $arrayAccessors .= '.0';
        $type = substr($type, 0, -2);
    }
    if (empty($parent)) {
        $parentPath = "$name$arrayAccessors";
    } else {
        $parentPath = "$parent$arrayAccessors.$name";
 }
@endphp
@if(!empty($subfield['fields']))
@component('scribe::partials.body-parameters', ['parameters' => [$subfield['name'] => $subfield], 'parent' => $parentPath])
@endcomponent
@else
<p>
@component('scribe::components.field-details', [
  'name' => $subfieldName,
  'type' => $subfield['type'] ?? 'string',
  'required' => $subfield['required'] ?? false,
  'description' => $subfield['description'] ?? '',
  'endpointId' => $endpointId,
  'parent' => $parentPath,
  'hasChildren' => false,
  'component' => 'body',
])
@endcomponent
</p>
@endif
@endforeach
</details>
</p>
@else
<p>
@component('scribe::components.field-details', [
  'name' => $name,
  'type' => $parameter['type'] ?? 'string',
  'required' => $parameter['required'] ?? false,
  'description' => $parameter['description'] ?? '',
  'endpointId' => $endpointId,
  'hasChildren' => false,
  'component' => 'body',
])
@endcomponent
</p>
@endif
@endforeach

