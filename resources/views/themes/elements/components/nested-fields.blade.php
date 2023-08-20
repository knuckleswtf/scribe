@php
    $level ??= 0;
    $levelNestingClass = match($level) {
        0 => "sl-ml-px",
        default => "sl-ml-7"
    };
    $expandable ??= !isset($fields["[]"]);
@endphp

@foreach($fields as $name => $field)
    <div class="{{ $expandable ? 'expandable' : '' }} sl-text-sm sl-border-l {{ $levelNestingClass }}">
        @component('scribe::themes.elements.components.field-details', [
          'name' => $name,
          'type' => $field['type'] ?? 'string',
          'required' => $field['required'] ?? false,
          'description' => $field['description'] ?? '',
          'example' => $field['example'] ?? '',
          'enumValues' => $field['enumValues'] ?? null,
          'endpointId' => $endpointId,
          'hasChildren' => !empty($field['__fields']),
          'component' => 'body',
        ])
        @endcomponent

        @if(!empty($field['__fields']))
            <div class="children" style="{{ $expandable ? 'display: none;' : '' }}">
                @component('scribe::themes.elements.components.nested-fields', [
                  'fields' => $field['__fields'],
                  'endpointId' => $endpointId,
                  'level' => $level + 1,
                  'expandable'=> $expandable,
                ])
                @endcomponent
            </div>
        @endif
    </div>
@endforeach
