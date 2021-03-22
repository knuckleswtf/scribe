<?php

namespace Knuckles\Scribe\Writing;

use Illuminate\Support\Facades\View;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Tools\Globals;
use Knuckles\Scribe\Tools\Utils;
use Knuckles\Scribe\Tools\WritingUtils;
use Parsedown;

/**
 * Transforms the extracted data (endpoints YAML, API details Markdown) into a HTML site
 */
class HtmlWriter
{
    /**
     * @var DocumentationConfig
     */
    protected $config;

    /** @var string */
    protected $baseUrl;

    /**
     * @var Parsedown
     */
    protected $markdownParser;

    public function __construct(DocumentationConfig $config = null)
    {
        $this->config = $config ?: new DocumentationConfig(config('scribe', []));
        $this->markdownParser = new Parsedown();
        $this->baseUrl = $this->config->get('base_url') ?? config('app.url');
    }

    public function generate(array $groupedEndpoints, string $sourceFolder, string $destinationFolder)
    {
        $index = $this->transformMarkdownFileToHTML($sourceFolder . '/index.md');
        $authentication = $this->transformMarkdownFileToHTML($sourceFolder . '/authentication.md');

        $prependFile = rtrim($sourceFolder, '/') . '/' . 'prepend.md';
        $appendFile = rtrim($sourceFolder, '/') . '/' . 'append.md';
        $prepend = file_exists($prependFile) ? $this->transformMarkdownFileToHTML($prependFile) : '';
        $append = file_exists($appendFile) ? $this->transformMarkdownFileToHTML($appendFile) : '';

        $theme = $this->config->get('theme') ?? 'default';
        $output = View::make("scribe::themes.$theme.index", [
            'metadata' => $this->getMetadata(),
            'baseUrl' => $this->baseUrl,
            'isInteractive' => $this->config->get('interactive', true),
            'prepend' => $prepend,
            'index' => $index,
            'authentication' => $authentication,
            'groupedEndpoints' => $groupedEndpoints,
            'append' => $append,
        ])->render();

        if (!is_dir($destinationFolder)) {
            mkdir($destinationFolder, 0777, true);
        }

        file_put_contents($destinationFolder . '/index.html', $output);

        // Copy assets
        $assetsFolder = __DIR__ . '/../../resources';
        if (!is_dir($destinationFolder . "/js")) {
            mkdir($destinationFolder."/js", 0777, true);
        }
        Utils::copyDirectory($assetsFolder . '/images/', $destinationFolder . '/images');
        Utils::copyDirectory($assetsFolder . '/css/', $destinationFolder . '/css');
        copy($assetsFolder . "/js/theme-$theme.js", $destinationFolder . WritingUtils::getVersionedAsset("/js/theme-$theme.js"));
        copy($assetsFolder . "/js/highlight.pack.js", $destinationFolder . "/js/highlight.pack.js");
        copy($assetsFolder . '/js/tryitout.js', $destinationFolder . WritingUtils::getVersionedAsset('/js/tryitout.js'));
    }

    protected function transformMarkdownFileToHTML(string $markdownFilePath): string
    {
        return $this->markdownParser->text(file_get_contents($markdownFilePath));
    }

    protected function getMetadata(): array
    {
        // NB:These paths are wrong for laravel type but will be set correctly by the Writer class
        $links = [];
        if ($this->config->get('postman.enabled', true)) {
            $links[] = '<a href="./collection.json">View Postman collection</a>';
        }
        if ($this->config->get('openapi.enabled', false)) {
            $links[] = '<a href="./openapi.yaml">View OpenAPI spec</a>';
        }

        $auth = $this->config->get('auth');
        if ($auth['in'] === 'bearer' || $auth['in'] === 'basic') {
            $auth['name'] = 'Authorization';
            $auth['location'] = 'header';
            $auth['prefix'] = ucfirst($auth['in']).' ';
        } else {
            $auth['location'] = $auth['in'];
            $auth['prefix'] = '';
        }

        return [
            'title' => $this->config->get('title') ?: config('app.name', '') . ' Documentation',
            'example_languages' => $this->config->get('example_languages'),
            'logo' => $this->config->get('logo') ?? false,
            'last_updated' => date("F j Y"),
            'auth' => $auth,
            'interactive' => $this->config->get('interactive', true),
            'links' => $links + ['<a href="http://github.com/knuckleswtf/scribe">Documentation powered by Scribe ✍</a>'],
        ];
    }
}
