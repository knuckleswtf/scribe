@foreach($groupedEndpoints as $group)
    <h1 id="{!! Str::slug($group['name']) !!}">{!! $group['name'] !!}</h1>

    {!! Parsedown::instance()->text($group['description']) !!}

    @foreach($group['endpoints'] as $endpoint)
        @include("scribe::themes.default.endpoint")
    @endforeach
@endforeach

