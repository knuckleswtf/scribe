
<div data-level="{{ $level ??= 0 }}" class="sl-text-sm sl-ml-px sl-border-l">
    @foreach($fields as $name => $field)
        @component('scribe::themes.elements.components.field-details', [
          'name' => $name,
          'type' => $field['type'] ?? 'string',
          'required' => $field['required'] ?? false,
          'description' => $field['description'] ?? '',
          'example' => $field['example'] ?? '',
          'endpointId' => $endpointId,
          'hasChildren' => !empty($field['__fields']),
          'component' => 'body',
        ])
        @endcomponent

        @if(!empty($field['__fields']))
            @component('scribe::themes.elements.components.nested-fields', [
              'fields' => $field['__fields'],
              'endpointId' => $endpointId,
              'level' => $level + 1,
            ])
            @endcomponent
        @endif
    @endforeach
</div>
