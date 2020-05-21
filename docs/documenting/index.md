# Documenting your API
Scribe tries to infer information about your API from your code, but you can enrich this information in the config and by using annotations (tags in doc block comments).

```eval_rst
.. toctree::
   :maxdepth: 2

   documenting-api-information
   documenting-endpoint-metadata
   documenting-endpoint-headers.md
   documenting-endpoint-query-parameters.md
   documenting-endpoint-body-parameters.md
   documenting-endpoint-responses.md
```

## Excluding endpoints from the documentation
You can exclude endpoints from the documentation by using the `@hideFromAPIDocumentation` tag in the method or class doc block. Scribe will not extract any information about the route or add it to the generated docs.
