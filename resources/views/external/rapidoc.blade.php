<!-- See https://rapidocweb.com/api.html for options -->
<!doctype html> <!-- Important: must specify -->
<html>
<head>
    <meta charset="utf-8"> <!-- Important: rapi-doc uses utf8 characters -->
    <script type="module" src="https://unpkg.com/rapidoc/dist/rapidoc-min.js"></script>
</head>
<body>
<rapi-doc spec-url="{!! $metadata['openapi_spec_url'] !!}"
          render-style="read"
          allow-try="{!! $metadata['try_it_out']['enabled'] == true ? 'true' : 'false'!!}"
>
    @if($metadata['logo'] ?: '')
        <img slot="logo" src="{!! $metadata['logo'] ?: '' !!}"/>
    @endif
</rapi-doc>
</body>
</html>
