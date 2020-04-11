# {!! $groupName !!}
{!! $groupDescription !!}

@foreach($routes as $route)
{!! $route['output'] !!}
@endforeach

