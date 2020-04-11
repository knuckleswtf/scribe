title: {{ $settings['title'] }}

language_tabs:
@foreach($settings['languages'] as $language)
- {{ $language }}
@endforeach

includes:
- "./authentication.md"
- "./groups/*"
- "./errors.md"
- "./extra.md"

logo: {{ $settings['logo'] ?? false }}

toc_footers:
@if($showPostmanCollectionButton)
- <a href="{{ $postmanCollectionLink }}">View Postman Collection</a>
@endif
- <a href='http://github.com/knuckleswtf/scribe'>Documentation powered by Scribe ✍</a>
