# Documenting your API
Scribe tries to infer information about your API from your code, but you can enrich this information in the config and by using annotations (tags in doc block comments).

Here's some of the information you can customise:
- [General API information](documenting-api-information.html)
- [Endpoint metadata](documenting-endpoint-metadata.html) (endpoint group, title, description, authentication status)
- [Headers to be sent in requests to the endpoint](documenting-endpoint-headers.html)
- [Query parameters and URL parameters to be sent in requests to the endpoint](documenting-endpoint-query-parameters.html)
- [Body parameters and files to be sent in requests to the endpoint](documenting-endpoint-body-parameters.html)
- [Example responses returned from the endpoint](documenting-endpoint-responses.html)

## Excluding endpoints from the documentation
You can exclude endpoints from the documentation by using the `@hideFromAPIDocumentation` tag in the method or class doc block. Scribe will not extract any information about the route or add it to the generated docs.
