@foreach($groupedEndpoints as $group)
    <h1 id="{!! Str::slug($group['name']) !!}"
        class="sl-text-5xl sl-leading-tight sl-font-prose sl-font-semibold sl-text-heading"
    >
        {!! $group['name'] !!}
    </h1>

    {!! Parsedown::instance()->text($group['description']) !!}

    @foreach($group['subgroups'] as $subgroupName => $subgroup)
        @if($subgroupName !== "")
            <h2 id="{!! Str::slug($group['name']) !!}-{!! Str::slug($subgroupName) !!}"
                class="sl-text-3xl sl-leading-tight sl-font-prose sl-font-semibold sl-text-heading"
            >
                {{ $subgroupName }}
            </h2>
            @php($subgroupDescription = collect($subgroup)->first(fn ($e) => $e->metadata->subgroupDescription)?->metadata?->subgroupDescription)
            @if($subgroupDescription)
                <div>
                <p class="">
                    {!! Parsedown::instance()->text($subgroupDescription) !!}
                </p>
                </div>
            @endif
        @endif
        @foreach($subgroup as $endpoint)
            @include("scribe::themes.elements.endpoint")
        @endforeach
    @endforeach
@endforeach

