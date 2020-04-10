title: API Reference

language_tabs:
@foreach($settings['languages'] as $language)
- {{ $language }}
@endforeach

includes:

logo: {{ $settings['logo'] ?? false }}

toc_footers:
- <a href='http://github.com/knuckleswtf/pastel'>Documentation powered by Pastel</a>
