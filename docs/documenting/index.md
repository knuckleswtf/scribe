# Documenting your API
Scribe tries to infer information about your API from your code, but you can enrich this information in the config and by using annotations (tags in doc block comments).

## Contents
* [Adding general information about your API](../documenting-api-information.md)
* [Documenting endpoint metadata](../documenting-endpoint-metadata.md)
* [Specifying headers to be sent in requests to the endpoint](../documenting-endpoint-headers.md)
* [Documenting query parameters and URL parameters to be sent in requests to the endpoint](../documenting-endpoint-query-parameters.md)
* [Documenting body parameters and files to be sent in requests to the endpoint](../documenting-endpoint-body-parameters.md)
* [Documenting possible responses returned from the endpoint](../documenting-endpoint-responses.md)

## Excluding endpoints from the documentation
You can exclude endpoints from the documentation by using the `@hideFromAPIDocumentation` tag in the method or class doc block. Scribe will not extract any information about the route or add it to the generated docs.
