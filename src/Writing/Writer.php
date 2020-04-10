<?php

namespace Knuckles\Scribe\Writing;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Knuckles\Pastel\Pastel;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Tools\Flags;
use Shalvah\Clara\Clara;

class Writer
{
    /**
     * @var Clara
     */
    protected $clara;

    /**
     * @var DocumentationConfig
     */
    private $config;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var bool
     */
    private $forceIt;

    /**
     * @var bool
     */
    private $shouldGeneratePostmanCollection = true;

    /**
     * @var Pastel
     */
    private $pastel;

    /**
     * @var bool
     */
    private $isStatic;

    /**
     * @var string
     */
    private $sourceOutputPath;

    /**
     * @var string
     */
    private $outputPath;

    public function __construct(DocumentationConfig $config = null, bool $forceIt = false)
    {
        // If no config is injected, pull from global
        $this->config = $config ?: new DocumentationConfig(config('scribe'));
        $this->baseUrl = $this->config->get('base_url') ?? config('app.url');
        $this->forceIt = $forceIt;
        $this->clara = clara('knuckleswtf/scribe',  Flags::$shouldBeVerbose)->only();
        $this->shouldGeneratePostmanCollection = $this->config->get('postman.enabled', false);
        $this->pastel = new Pastel();
        $this->isStatic = $this->config->get('type') === 'static';
        $this->sourceOutputPath = 'resources/docs';
        $this->outputPath = $this->isStatic ? 'public/docs' : 'resources/views/scribe';
    }

    public function writeDocs(Collection $routes)
    {
        // The source files (index.md, js/, css/, and images/) always go in resources/docs/source.
        // The static assets (js/, css/, and images/) always go in public/docs/.
        // For 'static' docs, the output files (index.html, collection.json) go in public/docs/.
        // For 'laravel' docs, the output files (index.blade.php, collection.json)
        // go in resources/views/scribe/ and storage/app/scribe/ respectively.

        $this->writeMarkdownAndSourceFiles($routes);

        $this->writeHtmlDocs();

        $this->writePostmanCollection($routes);
    }

    /**
     * @param  Collection $parsedRoutes
     *
     * @return void
     */
    public function writeMarkdownAndSourceFiles(Collection $parsedRoutes)
    {
        $targetFile = $this->sourceOutputPath . '/source/index.md';
        $compareFile = $this->sourceOutputPath . '/source/.compare.md';

        $settings = [
            'languages' => $this->config->get('example_languages'),
            'logo' => $this->config->get('logo'),
            'title' => config('app.name', '').' API Documentation',
        ];
        // Generate Markdown for each route
        $parsedRouteOutput = $this->generateMarkdownOutputForEachRoute($parsedRoutes, $settings);

        $frontmatter = view('scribe::partials.frontmatter')
            ->with('showPostmanCollectionButton', $this->shouldGeneratePostmanCollection)
             // This path is wrong for laravel type but will be replaced in post
            ->with('postmanCollectionLink', './collection.json')
            ->with('outputPath', 'docs')
            ->with('settings', $settings);

        /*
         * If the target file already exists,
         * we check if the documentation was modified
         * and skip the modified parts of the routes.
         */
        if (file_exists($targetFile) && file_exists($compareFile)) {
            $generatedDocumentation = file_get_contents($targetFile);
            $compareDocumentation = file_get_contents($compareFile);

            $parsedRouteOutput->transform(function (Collection $routeGroup) use ($generatedDocumentation, $compareDocumentation) {
                return $routeGroup->transform(function (array $route) use ($generatedDocumentation, $compareDocumentation) {
                    if (preg_match('/<!-- START_' . $route['id'] . ' -->(.*)<!-- END_' . $route['id'] . ' -->/is', $generatedDocumentation, $existingRouteDoc)) {
                        $routeDocumentationChanged = (preg_match('/<!-- START_' . $route['id'] . ' -->(.*)<!-- END_' . $route['id'] . ' -->/is', $compareDocumentation, $lastDocWeGeneratedForThisRoute) && $lastDocWeGeneratedForThisRoute[1] !== $existingRouteDoc[1]);
                        if ($routeDocumentationChanged === false || $this->forceIt) {
                            if ($routeDocumentationChanged) {
                                $this->clara->warn('Discarded manual changes for route [' . implode(',', $route['methods']) . '] ' . $route['uri']);
                            }
                        } else {
                            $this->clara->warn('Skipping modified route [' . implode(',', $route['methods']) . '] ' . $route['uri']);
                            $route['modified_output'] = $existingRouteDoc[0];
                        }
                    }

                    return $route;
                });
            });
        }


        $this->clara->info('Writing source Markdown files to: ' . $this->sourceOutputPath);

        $introMarkdown = view('scribe::index')
            ->with('frontmatter', $frontmatter);

        if (! is_dir($this->sourceOutputPath. '/source')) {
            mkdir($this->sourceOutputPath . '/source', 0777, true);
        }

        file_put_contents($targetFile, $introMarkdown);

        $authMarkdown = view('scribe::1-authentication');
        file_put_contents($this->sourceOutputPath . '/source/1-authentication.md', $authMarkdown);

        $endpointsMarkdown = view('scribe::2-endpoints')
            ->with('writeCompareFile', false)
            ->with('parsedRoutes', $parsedRouteOutput);
        file_put_contents($this->sourceOutputPath . '/source/2-endpoints.md', $endpointsMarkdown);


        // Write comparable markdown file
        $compareMarkdown = view('scribe::index')
            ->with('writeCompareFile', true)
            ->with('frontmatter', $frontmatter)
            ->with('outputPath', $this->config->get('output'))
            ->with('showPostmanCollectionButton', $this->shouldGeneratePostmanCollection)
            ->with('parsedRoutes', $parsedRouteOutput);

        file_put_contents($compareFile, $compareMarkdown);

        $this->clara->info('Wrote source Markdown files to: ' . $this->sourceOutputPath);
    }

    public function generateMarkdownOutputForEachRoute(Collection $parsedRoutes, array $settings): Collection
    {
        $parsedRouteOutput = $parsedRoutes->map(function (Collection $routeGroup) use ($settings) {
            return $routeGroup->map(function (array $route) use ($settings) {
                if (count($route['cleanBodyParameters']) && ! isset($route['headers']['Content-Type'])) {
                    // Set content type if the user forgot to set it
                    $route['headers']['Content-Type'] = 'application/json';
                }

                $hasRequestOptions = ! empty($route['headers']) || ! empty($route['cleanQueryParameters']) || ! empty($route['cleanBodyParameters']);
                $route['output'] = (string) view('scribe::partials.route')
                    ->with('hasRequestOptions', $hasRequestOptions)
                    ->with('route', $route)
                    ->with('settings', $settings)
                    ->with('baseUrl', $this->baseUrl)
                    ->render();

                return $route;
            });
        });

        return $parsedRouteOutput;
    }

    protected function writePostmanCollection(Collection $parsedRoutes): void
    {
        if ($this->shouldGeneratePostmanCollection) {
            $this->clara->info('Generating Postman collection');

            $collection = $this->generatePostmanCollection($parsedRoutes);
            if ($this->isStatic) {
                $collectionPath = "{$this->outputPath}/collection.json";
                file_put_contents($collectionPath, $collection);
            } else {
                Storage::disk('local')->put('scribe/collection.json', $collection);
                $collectionPath = 'storage/app/scribe/collection.json';
            }

            $this->clara->success("Wrote Postman collection to: {$collectionPath}");
        }
    }

    /**
     * Generate Postman collection JSON file.
     *
     * @param Collection $routes
     *
     * @return string
     */
    public function generatePostmanCollection(Collection $routes)
    {
        /** @var PostmanCollectionWriter $writer */
        $writer = app()->makeWith(
            PostmanCollectionWriter::class,
            ['routeGroups' => $routes, 'baseUrl' => $this->baseUrl]
        );

        return $writer->getCollection();
    }

    protected function moveOutputFromPublicFolderToResourcesFolder(): void
    {
            // Move output to resources/views
            if (! is_dir($this->outputPath)) {
                mkdir($this->outputPath);
            }
            rename("public/docs/index.html", "$this->outputPath/index.blade.php");
            $contents = file_get_contents("$this->outputPath/index.blade.php");
            //
            $contents = str_replace('href="css/style.css"', 'href="/docs/css/style.css"', $contents);
            $contents = str_replace('src="js/all.js"', 'src="/docs/js/all.js"', $contents);
            $contents = str_replace('src="images/', 'src="/docs/images/', $contents);
            $contents = preg_replace('#href="./collection.json"#', 'href="{{ route("scribe.json") }}"', $contents);
            file_put_contents("$this->outputPath/index.blade.php", $contents);
    }

    public function writeHtmlDocs(): void
    {
        $this->clara->info('Generating API HTML code');

        $this->pastel->generate($this->sourceOutputPath. '/source/index.md', 'public/docs');

        if (! $this->isStatic) {
            $this->moveOutputFromPublicFolderToResourcesFolder();
        }

        $this->clara->success("Wrote HTML documentation to: {$this->outputPath}");
    }
}
