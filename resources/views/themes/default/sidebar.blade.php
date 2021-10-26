@foreach($groupedEndpoints as $group)
<ul id="tocify-header{{ $loop->index }}" class="tocify-header">
    <li class="tocify-item" data-unique="{!! Str::slug($group['name']) !!}">
        <a href="#{!! Str::slug($group['name']) !!}">{!! $group['name'] !!}</a>
    </li>
    @foreach($group['endpoints'] as $endpoint)
    <ul class="tocify-subheader" data-tag="{{ $loop->index }}">
        <li class="tocify-item" data-unique="{!! Str::slug($group['name']) !!}-{!! $endpoint->endpointId() !!}">
            <a href="#{!! Str::slug($group['name']) !!}-{!! $endpoint->endpointId() !!}">{{ $endpoint->metadata->title ?: ($endpoint->httpMethods[0]." ".$endpoint->uri)}}</a>
        </li>
    </ul>
    @endforeach
</ul>
@endforeach
