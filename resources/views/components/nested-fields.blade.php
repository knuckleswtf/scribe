@php
    $isInput ??= true
@endphp
@foreach($fields as $name => $field)
    @if($name === '[]')
        @php
            $description = "The request body is an array (<code>{$field['type']}</code>`)";
            $description .= !empty($field['description']) ? ", representing ".lcfirst($field['description'])."." : '.';
        @endphp
        <p>
            {!! Parsedown::instance()->text($description) !!}
        </p>
        @foreach($field['__fields'] as $subfieldName => $subfield)
                @if(!empty($subfield['__fields']))
                    <x-scribe::nested-fields
                            :fields="[$subfieldName => $subfield]" :endpointId="$endpointId" :isInput="$isInput"
                    />
                @else
                    <p>
                        @component('scribe::components.field-details', [
                          'name' => $subfield['name'],
                          'type' => $subfield['type'] ?? 'string',
                          'required' => $subfield['required'] ?? false,
                          'description' => $subfield['description'] ?? '',
                          'example' => $subfield['example'] ?? '',
                          'endpointId' => $endpointId,
                          'hasChildren' => false,
                          'component' => 'body',
                          'isInput' => $isInput,
                        ])
                        @endcomponent
                    </p>
                @endif
            @endforeach
    @elseif(!empty($field['__fields']))
        <p>
        <details>
            <summary style="padding-bottom: 10px;">
                @component('scribe::components.field-details', [
                  'name' => $field['name'],
                  'type' => $field['type'] ?? 'string',
                  'required' => $field['required'] ?? false,
                  'description' => $field['description'] ?? '',
                  'example' => $field['example'] ?? '',
                  'endpointId' => $endpointId,
                  'hasChildren' => true,
                  'component' => 'body',
                  'isInput' => $isInput,
                ])
                @endcomponent
            </summary>
            @foreach($field['__fields'] as $subfieldName => $subfield)
                @if(!empty($subfield['__fields']))
                    <x-scribe::nested-fields
                            :fields="[$subfieldName => $subfield]" :endpointId="$endpointId" :isInput="$isInput"
                    />
                @else
                    <p>
                        @component('scribe::components.field-details', [
                          'name' => $subfield['name'],
                          'type' => $subfield['type'] ?? 'string',
                          'required' => $subfield['required'] ?? false,
                          'description' => $subfield['description'] ?? '',
                          'example' => $subfield['example'] ?? '',
                          'endpointId' => $endpointId,
                          'hasChildren' => false,
                          'component' => 'body',
                          'isInput' => $isInput,
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
              'name' => $field['name'],
              'type' => $field['type'] ?? 'string',
              'required' => $field['required'] ?? false,
              'description' => $field['description'] ?? '',
              'example' => $field['example'] ?? '',
              'endpointId' => $endpointId,
              'hasChildren' => false,
              'component' => 'body',
              'isInput' => $isInput,
            ])
            @endcomponent
        </p>
    @endif
@endforeach
