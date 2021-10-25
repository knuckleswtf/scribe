
@foreach($parameters as $name => $parameter)
    @if($name === '[]')
        @php
            $description = "The request body is an array (<code>{$parameter['type']}</code>`)";
            $description .= !empty($parameter['description']) ? ", representing ".lcfirst($parameter['description'])."." : '.';
        @endphp
        <p>
            {!! Parsedown::instance()->text($description) !!}
        </p>
        @foreach($parameter['__fields'] as $subfieldName => $subfield)
                @if(!empty($subfield['__fields']))
                    @component('scribe::components.body-parameters', ['parameters' => [$subfieldName => $subfield], 'endpointId' => $endpointId,])
                    @endcomponent
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
                        ])
                        @endcomponent
                    </p>
                @endif
            @endforeach
    @elseif(!empty($parameter['__fields']))
        <p>
        <details>
            <summary style="padding-bottom: 10px;">
                @component('scribe::components.field-details', [
                  'name' => $parameter['name'],
                  'type' => $parameter['type'] ?? 'string',
                  'required' => $parameter['required'] ?? false,
                  'description' => $parameter['description'] ?? '',
                  'example' => $parameter['example'] ?? '',
                  'endpointId' => $endpointId,
                  'hasChildren' => true,
                  'component' => 'body',
                ])
                @endcomponent
            </summary>
            @foreach($parameter['__fields'] as $subfieldName => $subfield)
                @if(!empty($subfield['__fields']))
                    @component('scribe::components.body-parameters', ['parameters' => [$subfieldName => $subfield], 'endpointId' => $endpointId,])
                    @endcomponent
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
              'name' => $parameter['name'],
              'type' => $parameter['type'] ?? 'string',
              'required' => $parameter['required'] ?? false,
              'description' => $parameter['description'] ?? '',
              'example' => $parameter['example'] ?? '',
              'endpointId' => $endpointId,
              'hasChildren' => false,
              'component' => 'body',
            ])
            @endcomponent
        </p>
    @endif
@endforeach
