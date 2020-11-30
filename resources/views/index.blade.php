---
{!! $frontmatter !!}
---

# Introduction

{!! $description !!}

{!! $introText !!}

@if($isInteractive)
<script src="https://cdn.jsdelivr.net/npm/lodash@4.17.10/lodash.min.js"></script>
<script>
    var baseUrl = "{{ $baseUrl }}";
</script>
<script src="js/tryitout-{{ \Knuckles\Scribe\Tools\Globals::SCRIBE_VERSION }}.js"></script>
@endif

> Base URL

```yaml
{!! $baseUrl !!}
```