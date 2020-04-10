title: API Reference

language_tabs:
@foreach($settings['languages'] as $language)
- {{ $language }}
@endforeach

includes:

logo: {{ $settings['logo'] ?? false }}

toc_footers:
@if($showPostmanCollectionButton)
- <a href="{{ $postmanCollectionLink }}">Get Postman Collection</a>
@endif
- <a href='http://github.com/knuckleswtf/scribe'>Documentation powered by Scribe</a>
