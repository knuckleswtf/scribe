@foreach($groupedEndpoints as $group)
    <h1 id="{!! Str::slug($group['name'], config('scribe.language', 'en')) !!}">{!! $group['name'] !!}</h1>

    {!! Parsedown::instance()->text($group['description']) !!}

    @foreach($group['subgroups'] as $subgroupName => $subgroup)
        @if($subgroupName !== "")
            <h2 id="{!! Str::slug($group['name'], config('scribe.language', 'en')) !!}-{!! Str::slug($subgroupName, config('scribe.language', 'en')) !!}">{{ $subgroupName }}</h2>
            @php($subgroupDescription = collect($subgroup)->first(fn ($e) => $e->metadata->subgroupDescription)?->metadata?->subgroupDescription)
            @if($subgroupDescription)
                <p>
                    {!! Parsedown::instance()->text($subgroupDescription) !!}
                </p>
            @endif
        @endif
        @foreach($subgroup as $endpoint)
            @include("scribe::themes.default.endpoint")
        @endforeach
    @endforeach
@endforeach

