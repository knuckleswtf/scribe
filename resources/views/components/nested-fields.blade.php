@php
    $isInput ??= true;
    $level ??= 0;
@endphp
@foreach($fields as $name => $field)
    @if($name === '[]')
        @php
            $description = "The request body is an array (<code>{$field['type']}</code>`)";
            $description .= !empty($field['description']) ? ", representing ".lcfirst($field['description'])."." : '.';
            if(count($field['__fields'])) $description .= " Each item has the following properties:";
        @endphp
        {!! Parsedown::instance()->text($description) !!}

        @foreach($field['__fields'] as $subfieldName => $subfield)
                @if(!empty($subfield['__fields']))
                    <x-scribe::nested-fields
                            :fields="[$subfieldName => $subfield]" :endpointId="$endpointId" :isInput="$isInput" :level="$level + 2"
                    />
                @else
                    <div style="margin-left: {{ ($level + 2) * 14 }}px; clear: unset;">
                        @component('scribe::components.field-details', [
                          'name' => $subfieldName,
                          'fullName' => $subfield['name'],
                          'type' => $subfield['type'] ?? 'string',
                          'required' => $subfield['required'] ?? false,
                          'description' => $subfield['description'] ?? '',
                          'example' => $subfield['example'] ?? '',
                          'enumValues' => $subfield['enumValues'] ?? null,
                          'endpointId' => $endpointId,
                          'hasChildren' => false,
                          'component' => 'body',
                          'isInput' => $isInput,
                        ])
                        @endcomponent
                    </div>
                @endif
            @endforeach
    @elseif(!empty($field['__fields']))
        <div style="@if($level) margin-left: {{ $level * 14 }}px;@else padding-left: 28px; @endif clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                @component('scribe::components.field-details', [
                  'name' => $name,
                  'fullName' => $field['name'],
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
                            :fields="[$subfieldName => $subfield]" :endpointId="$endpointId" :isInput="$isInput" :level="$level + 1"
                    />
                @else
                    <div style="margin-left: {{ ($level + 1) * 14 }}px; clear: unset;">
                        @component('scribe::components.field-details', [
                          'name' => $subfieldName,
                          'fullName' => $subfield['name'],
                          'type' => $subfield['type'] ?? 'string',
                          'required' => $subfield['required'] ?? false,
                          'description' => $subfield['description'] ?? '',
                          'example' => $subfield['example'] ?? '',
                          'enumValues' => $subfield['enumValues'] ?? null,
                          'endpointId' => $endpointId,
                          'hasChildren' => false,
                          'component' => 'body',
                          'isInput' => $isInput,
                        ])
                        @endcomponent
                    </div>
                @endif
            @endforeach
        </details>
        </div>
    @else
        <div style="@if($level) margin-left: {{ ($level + 1) * 14 }}px;@else padding-left: 28px; @endif clear: unset;">
            @component('scribe::components.field-details', [
              'name' => $name,
              'fullName' => $field['name'],
              'type' => $field['type'] ?? 'string',
              'required' => $field['required'] ?? false,
              'description' => $field['description'] ?? '',
              'example' => $field['example'] ?? '',
              'enumValues' => $field['enumValues'] ?? null,
              'endpointId' => $endpointId,
              'hasChildren' => false,
              'component' => 'body',
              'isInput' => $isInput,
            ])
            @endcomponent
        </div>
    @endif
@endforeach
