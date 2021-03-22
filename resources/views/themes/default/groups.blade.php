@foreach($groupedEndpoints as $group)
    <h1 id="{!! Str::slug($group['name']) !!}">{!! $group['name'] !!}</h1>
    <p>
        {!! Parsedown::instance()->text($group['description']) !!}
    </p>

    @foreach($group['endpoints'] as $endpoint)
        @include("scribe::themes.default.endpoint")
    @endforeach
@endforeach

