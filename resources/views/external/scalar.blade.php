<!doctype html>
<html>
<head>
    <title>{!! $metadata['title'] !!}</title>
    <meta charset="utf-8"/>
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"/>
    <style>
        body {
            margin: 0;
        }
    </style>
</head>
<body>

<div id="app"></div>

<script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
<script>
    Scalar.createApiReference('#app', {!! $scalarConfig !!})
</script>
</body>
</html>
