<?php

namespace Knuckles\Scribe\Writing;

use Illuminate\Support\Facades\View;

/**
 * Writes a basic, mostly empty template, passing the OpenAPI spec URL in for an external client-side renderer.
 */
class ExternalHtmlWriter extends HtmlWriter
{
    public function generate(array $groupedEndpoints, string $sourceFolder, string $destinationFolder)
    {
        $template = $this->config->get('theme');
        $output = View::make("scribe::external.$template", [
            'metadata' => $this->getMetadata(),
            'baseUrl' => $this->baseUrl,
            'tryItOut' => $this->config->get('try_it_out'),
            'htmlAttributes' => $this->config->get('external.html_attributes', []),
        ])->render();

        if (!is_dir($destinationFolder)) {
            mkdir($destinationFolder, 0777, true);
        }

        file_put_contents($destinationFolder . '/index.html', $output);
    }

    public function getMetadata(): array
    {
        // NB:These paths are wrong for laravel type but will be set correctly by the Writer class
        if ($this->config->get('postman.enabled', true)) {
            $postmanCollectionUrl = "{$this->assetPathPrefix}collection.json";
        }
        if ($this->config->get('openapi.enabled', false)) {
            $openApiSpecUrl = "{$this->assetPathPrefix}openapi.yaml";
        }
        return [
            'title' => $this->config->get('title') ?: config('app.name', '') . ' Documentation',
            'example_languages' => $this->config->get('example_languages'), // may be useful
            'logo' => $this->config->get('logo') ?? false,
            'last_updated' => $this->getLastUpdated(), // may be useful
            'try_it_out' => $this->config->get('try_it_out'), // may be useful
            "postman_collection_url" => $postmanCollectionUrl ?? null,
            "openapi_spec_url" => $openApiSpecUrl ?? null,
        ];
    }

}
