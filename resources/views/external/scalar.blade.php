<!doctype html>
<html>

<head>
    <title>{!! $metadata['title'] !!}</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        body {
            margin: 0;
        }
    </style>
</head>

<body>

    <script id="api-reference" data-url="{!! $metadata['openapi_spec_url'] !!}"></script>
    <script>
        document.getElementById('api-reference').dataset.configuration = JSON.stringify({!! json_encode($htmlAttributes) !!});
    </script>
    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
</body>

</html>
