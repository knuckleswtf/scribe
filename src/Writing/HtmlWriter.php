<?php

namespace Knuckles\Scribe\Writing;

use http\Exception\InvalidArgumentException;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Tools\MarkdownParser;
use Knuckles\Scribe\Tools\Utils;
use Knuckles\Scribe\Tools\Utils as u;
use Knuckles\Scribe\Tools\WritingUtils;

/**
 * Transforms the extracted data (endpoints YAML, API details Markdown) into a HTML site
 */
class HtmlWriter
{
    protected DocumentationConfig $config;
    protected string $baseUrl;
    protected string $assetPathPrefix;
    protected MarkdownParser $markdownParser;

    public function __construct(DocumentationConfig $config = null)
    {
        $this->config = $config ?: new DocumentationConfig(config('scribe', []));
        $this->markdownParser = new MarkdownParser();
        $this->baseUrl = $this->config->get('base_url') ?? config('app.url');
        // If they're using the default static path,
        // then use '../docs/{asset}', so assets can work via Laravel app or via index.html
        $this->assetPathPrefix = '../docs/';
        if ($this->config->get('type') == 'static'
            && rtrim($this->config->get('static.output_path', ''), '/') != 'public/docs'
        ) {
            $this->assetPathPrefix = './';
        }
    }

    public function generate(array $groupedEndpoints, string $sourceFolder, string $destinationFolder)
    {
        $intro = $this->transformMarkdownFileToHTML($sourceFolder . '/intro.md');
        $auth = $this->transformMarkdownFileToHTML($sourceFolder . '/auth.md');
        $headingsBeforeEndpoints = $this->markdownParser->headings;

        $this->markdownParser->headings = [];
        $appendFile = rtrim($sourceFolder, '/') . '/' . 'append.md';
        $append = file_exists($appendFile) ? $this->transformMarkdownFileToHTML($appendFile) : '';
        $headingsAfterEndpoints = $this->markdownParser->headings;

        foreach ($groupedEndpoints as &$group) {
                $group['subgroups'] = collect($group['endpoints'])->groupBy('metadata.subgroup')->all();
        }
        $theme = $this->config->get('theme') ?? 'default';
        $output = View::make("scribe::themes.$theme.index", [
            'metadata' => $this->getMetadata(),
            'baseUrl' => $this->baseUrl,
            'tryItOut' => $this->config->get('try_it_out'),
            'intro' => $intro,
            'auth' => $auth,
            'groupedEndpoints' => $groupedEndpoints,
            'headings' => $this->getHeadings($headingsBeforeEndpoints, $groupedEndpoints, $headingsAfterEndpoints),
            'append' => $append,
            'assetPathPrefix' => $this->assetPathPrefix,
        ])->render();

        if (!is_dir($destinationFolder)) {
            mkdir($destinationFolder, 0777, true);
        }

        file_put_contents($destinationFolder . '/index.html', $output);

        // Copy assets
        $assetsFolder = __DIR__ . '/../../resources';
        // Prune older versioned assets
        if (is_dir($destinationFolder . '/css')) {
            Utils::deleteDirectoryAndContents($destinationFolder . '/css');
        }
        if (is_dir($destinationFolder . '/js')) {
            Utils::deleteDirectoryAndContents($destinationFolder . '/js');
        }
        Utils::copyDirectory("{$assetsFolder}/images/", "{$destinationFolder}/images");

        $assets = [
            "{$assetsFolder}/css/theme-$theme.style.css" => ["$destinationFolder/css/", "theme-$theme.style.css"],
            "{$assetsFolder}/css/theme-$theme.print.css" => ["$destinationFolder/css/", "theme-$theme.print.css"],
            "{$assetsFolder}/js/theme-$theme.js" => ["$destinationFolder/js/", WritingUtils::getVersionedAsset("theme-$theme.js")],
        ];

        if ($this->config->get('try_it_out.enabled', true)) {
            $assets["{$assetsFolder}/js/tryitout.js"] = ["$destinationFolder/js/", WritingUtils::getVersionedAsset('tryitout.js')];
        }

        foreach ($assets as $path => [$destination, $fileName]) {
            if (file_exists($path)) {
                if (!is_dir($destination)) {
                    mkdir($destination, 0777, true);
                }
                copy($path, $destination . $fileName);
            }
        }
    }

    protected function transformMarkdownFileToHTML(string $markdownFilePath): string
    {
        return $this->markdownParser->text(file_get_contents($markdownFilePath));
    }

    public function getMetadata(): array
    {
        // todo remove 'links' in future
        $links = []; // Left for backwards compat

        // NB:These paths are wrong for laravel type but will be set correctly by the Writer class
        if ($this->config->get('postman.enabled', true)) {
            $links[] = "<a href=\"{$this->assetPathPrefix}collection.json\">".u::trans("scribe::links.postman")."</a>";
            $postmanCollectionUrl = "{$this->assetPathPrefix}collection.json";
        }
        if ($this->config->get('openapi.enabled', false)) {
            $links[] = "<a href=\"{$this->assetPathPrefix}openapi.yaml\">".u::trans("scribe::links.openapi")."</a>";
            $openApiSpecUrl = "{$this->assetPathPrefix}openapi.yaml";
        }

        $auth = $this->config->get('auth');
        if ($auth) {
            if ($auth['in'] === 'bearer' || $auth['in'] === 'basic') {
                $auth['name'] = 'Authorization';
                $auth['location'] = 'header';
                $auth['prefix'] = ucfirst($auth['in']) . ' ';
            } else {
                $auth['location'] = $auth['in'];
                $auth['prefix'] = '';
            }
        }

        return [
            'title' => $this->config->get('title') ?: config('app.name', '') . ' Documentation',
            'example_languages' => $this->config->get('example_languages'),
            'logo' => $this->config->get('logo') ?? false,
            'last_updated' => $this->getLastUpdated(),
            'auth' => $auth,
            'try_it_out' => $this->config->get('try_it_out'),
            "postman_collection_url" => $postmanCollectionUrl ?? null,
            "openapi_spec_url" => $openApiSpecUrl ?? null,
            'links' => array_merge($links, ['<a href="http://github.com/knuckleswtf/scribe">Documentation powered by Scribe ✍</a>']),
        ];
    }

    protected function getLastUpdated()
    {
        $lastUpdated = $this->config->get('last_updated', 'Last updated: {date:F j, Y}');

        $tokens = [
            "date" => fn($format) => date($format),
            "git" => fn($format) => match ($format) {
                "short" => trim(shell_exec('git rev-parse --short HEAD')),
                "long" => trim(shell_exec('git rev-parse HEAD')),
                default => throw new InvalidArgumentException("The `git` token only supports formats 'short' and 'long', but you specified $format"),
            },
        ];

        foreach ($tokens as $token => $resolver) {
            $matches = [];
            if(preg_match('#(\{'.$token.':(.+?)})#', $lastUpdated, $matches)) {
                $lastUpdated = str_replace($matches[1], $resolver($matches[2]), $lastUpdated);
            }
        }

        return $lastUpdated;
    }

    protected function getHeadings(array $headingsBeforeEndpoints, array $endpointsByGroupAndSubgroup, array $headingsAfterEndpoints)
    {
        $headings = [];

        $lastL1ElementIndex = null;
        foreach ($headingsBeforeEndpoints as $heading) {
            $element = [
                'slug' => $heading['slug'],
                'name' => $heading['text'],
                'subheadings' => [],
            ];;
            if ($heading['level'] === 1) {
                $headings[] = $element;
                $lastL1ElementIndex = count($headings) - 1;
            } elseif ($heading['level'] === 2 && !is_null($lastL1ElementIndex)) {
                $headings[$lastL1ElementIndex]['subheadings'][] = $element;
            }
        }

        $headings = array_merge($headings, array_values(array_map(function ($group) {
            $groupSlug = Str::slug($group['name']);

            return [
                'slug' => $groupSlug,
                'name' => $group['name'],
                'subheadings' => collect($group['subgroups'])->flatMap(function ($endpoints, $subgroupName) use ($groupSlug) {
                    if ($subgroupName === "") {
                        return $endpoints->map(fn(OutputEndpointData $endpoint) => [
                            'slug' => $endpoint->fullSlug(),
                            'name' => $endpoint->name(),
                            'subheadings' => []
                        ])->values();
                    }

                    return [
                        [
                            'slug' => "$groupSlug-" . Str::slug($subgroupName),
                            'name' => $subgroupName,
                            'subheadings' => $endpoints->map(fn($endpoint) => [
                                'slug' => $endpoint->fullSlug(),
                                'name' => $endpoint->name(),
                                'subheadings' => []
                            ])->values(),
                        ],
                    ];
                })->values(),
            ];
        }, $endpointsByGroupAndSubgroup)));

        $lastL1ElementIndex = null;
        foreach ($headingsAfterEndpoints as $heading) {
            $element = [
                'slug' => $heading['slug'],
                'name' => $heading['text'],
                'subheadings' => [],
            ];;
            if ($heading['level'] === 1) {
                $headings[] = $element;
                $lastL1ElementIndex = count($headings) - 1;
            } elseif ($heading['level'] === 2 && !is_null($lastL1ElementIndex)) {
                $headings[$lastL1ElementIndex]['subheadings'][] = $element;
            }
        }

        return $headings;
    }
}
