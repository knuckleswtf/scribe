title: {{ $settings['title'] }}

language_tabs:
@foreach($settings['languages'] as $language)
- {{ $language }}
@endforeach

includes:
- "./prepend.md"
- "./authentication.md"
- "./groups/*"
- "./errors.md"
- "./append.md"

logo: {{ $settings['logo'] ?? false }}

toc_footers:
@if($showPostmanCollectionButton)
- <a href="{{ $postmanCollectionLink }}">View Postman Collection</a>
@endif
@if($showOpenAPISpecButton)
- <a href="{{ $openAPISpecLink }}">View OpenAPI (Swagger) Spec</a>
@endif
- <a href='http://github.com/knuckleswtf/scribe'>Documentation powered by Scribe ✍</a>
