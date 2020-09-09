
@foreach($parameters as $name => $parameter)
@if(!empty($parameter['fields']))
<p>
<details>
<summary>
@component('scribe::components.field-details', [
  'name' => $name,
  'type' => $parameter['type'] ?? 'string',
  'required' => $parameter['required'] ?? true,
  'description' => $parameter['description'],
])
@endcomponent
</summary>
<br>
@foreach($parameter['fields'] as $subfieldName => $subfield)
@if(!empty($subfield['fields']))
@component('scribe::partials.body-parameters', ['parameters' => [$subfield['name'] => $subfield]])
@endcomponent
@else
<p>
@component('scribe::components.field-details', [
  'name' => $subfieldName,
  'type' => $subfield['type'] ?? 'string',
  'required' => $subfield['required'] ?? true,
  'description' => $subfield['description'],
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
  'required' => $parameter['required'] ?? true,
  'description' => $parameter['description'],
])
@endcomponent
</p>
@endif
@endforeach

