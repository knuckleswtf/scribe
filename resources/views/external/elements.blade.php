<!-- See https://github.com/stoplightio/elements/blob/main/docs/getting-started/elements/elements-options.md for config -->
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Elements in HTML</title>
    <!-- Embed elements Elements via Web Component -->
    <script src="https://unpkg.com/@stoplight/elements/web-components.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/@stoplight/elements/styles.min.css">
</head>
<body>

<elements-api
@foreach($htmlAttributes as $attribute => $value)
    {{-- Attributes specified first override later ones --}}
    {!! $attribute !!}="{!! $value !!}"
@endforeach
    apiDescriptionUrl="{!! $metadata['openapi_spec_url'] !!}"
    router="hash"
    layout="sidebar"
    hideTryIt="{!! ($tryItOut['enabled'] ?? true) ? '' : 'true'!!}"
    logo="{!! $metadata['logo'] !!}"
/>

</body>
</html>
