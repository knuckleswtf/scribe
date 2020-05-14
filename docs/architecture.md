# How Scribe works
Read this page if you want a deeper understanding of how Scribe works (for instance, for the purpose of contributing).

- When the `generate` command is run, the Generator fetches all your application's routes from Laravel's (or DIngo's) Route facade.
- Next, the RouteMatcher uses the rules in your config to determine what routes to generate documentation for, as well as extract any specific configuration for them. This configuration is passed to the next stages.
- The Generator processes each route. This means fetching the route action (controller, method) and using the configured strategies to extract the following:
  - route metadata (name, description, group name, group description, auth status)
  - url parameters
  - body parameters
  - query parameters
  - headers
  - fields in the response
  - sample responses
- Next, the Writer uses information from these parsed routes and other configuration to generate a Markdown file via Blade templating.
- This Markdown file is passed to [Pastel](https://github.com/knuckleswtf/pastel), which wraps them in a theme and converts them into HTML, CSS and JS.
- If enabled, a Postman collection is generated as wel, via the PostmanCollectionWriter.
