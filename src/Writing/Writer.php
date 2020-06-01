<?php

namespace Knuckles\Scribe\Writing;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Knuckles\Pastel\Pastel;
use Knuckles\Scribe\Tools\ConsoleOutputUtils;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Tools\Utils;

class Writer
{
    /**
     * @var DocumentationConfig
     */
    private $config;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var string
     */
    private $postmanBaseUrl;

    /**
     * @var bool
     */
    private $shouldOverwrite;

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
    private $sourceOutputPath = 'resources/docs';

    /**
     * @var string
     */
    private $staticTypeOutputPath;

    /**
     * @var string
     */
    private $laravelTypeOutputPath = 'resources/views/scribe';

    /**
     * @var string
     */
    private $fileModificationTimesFile;

    /**
     * @var array
     */
    private $lastTimesWeModifiedTheseFiles = [];

    public function __construct(DocumentationConfig $config = null, bool $shouldOverwrite = false)
    {
        // If no config is injected, pull from global. Makes testing easier.
        $this->config = $config ?: new DocumentationConfig(config('scribe'));
        $this->baseUrl = $this->config->get('base_url') ?? config('app.url');
        $this->postmanBaseUrl = $this->config->get('postman.base_url') ?? $this->baseUrl;
        $this->shouldOverwrite = $shouldOverwrite;
        $this->shouldGeneratePostmanCollection = $this->config->get('postman.enabled', false);
        $this->pastel = new Pastel();

        $this->isStatic = $this->config->get('type') === 'static';
        $this->staticTypeOutputPath = rtrim($this->config->get('static.output_path', 'public/docs'), '/');

        $this->fileModificationTimesFile = $this->sourceOutputPath . '/.filemtimes';
        $this->lastTimesWeModifiedTheseFiles = [];
    }

    public function writeDocs(Collection $routes = null)
    {
        // The source Markdown files always go in resources/docs.
        // The static assets (js/, css/, and images/) always go in public/docs/.
        // For 'static' docs, the output files (index.html, collection.json) go in public/docs/.
        // For 'laravel' docs, the output files (index.blade.php, collection.json)
        // go in resources/views/scribe/ and storage/app/scribe/ respectively.

        $routes && $this->writeMarkdownAndSourceFiles($routes);

        $this->writeHtmlDocs();

        $routes && $this->writePostmanCollection($routes);
    }

    /**
     * @param Collection $parsedRoutes
     *
     * @return void
     */
    public function writeMarkdownAndSourceFiles(Collection $parsedRoutes)
    {
        $settings = [
            'languages' => $this->config->get('example_languages'),
            'logo' => $this->config->get('logo'),
            'title' => $this->config->get('title', config('app.name', '') . ' API Documentation'),
        ];

        ConsoleOutputUtils::info('Writing source Markdown files to: ' . $this->sourceOutputPath);

        if (!is_dir($this->sourceOutputPath)) {
            mkdir($this->sourceOutputPath, 0777, true);
        }

        $this->fetchLastTimeWeModifiedFilesFromTrackingFile();

        $this->writeEndpointsMarkdownFile($parsedRoutes, $settings);
        $this->writeIndexMarkdownFile($settings);
        $this->writeAuthMarkdownFile();

        $this->writeModificationTimesTrackingFile();

        ConsoleOutputUtils::info('Wrote source Markdown files to: ' . $this->sourceOutputPath);
    }

    public function generateMarkdownOutputForEachRoute(Collection $parsedRoutes, array $settings): Collection
    {
        $routesWithOutput = $parsedRoutes->map(function (Collection $routeGroup) use ($settings) {
            return $routeGroup->map(function (array $route) use ($settings) {
                $hasRequestOptions = !empty($route['headers'])
                    || !empty($route['cleanQueryParameters'])
                    || !empty($route['cleanBodyParameters']);
                $route['output'] = (string)view('scribe::partials.endpoint')
                    ->with('hasRequestOptions', $hasRequestOptions)
                    ->with('route', $route)
                    ->with('settings', $settings)
                    ->with('baseUrl', $this->baseUrl)
                    ->render();

                return $route;
            });
        });

        return $routesWithOutput;
    }

    protected function writePostmanCollection(Collection $parsedRoutes): void
    {
        if ($this->shouldGeneratePostmanCollection) {
            ConsoleOutputUtils::info('Generating Postman collection');

            $collection = $this->generatePostmanCollection($parsedRoutes);
            if ($this->isStatic) {
                $collectionPath = "{$this->staticTypeOutputPath}/collection.json";
                file_put_contents($collectionPath, $collection);
            } else {
                Storage::disk('local')->put('scribe/collection.json', $collection);
                $collectionPath = 'storage/app/scribe/collection.json';
            }

            ConsoleOutputUtils::success("Wrote Postman collection to: {$collectionPath}");
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
            ['routeGroups' => $routes, 'baseUrl' => $this->postmanBaseUrl]
        );

        return $writer->makePostmanCollection();
    }

    protected function performFinalTasksForLaravelType(): void
    {
        if (!is_dir($this->laravelTypeOutputPath)) {
            mkdir($this->laravelTypeOutputPath);
        }
        if (!is_dir("public/vendor/scribe")) {
            mkdir("public/vendor/scribe", 0777, true);
        }

        // Transform output HTML to a Blade view
        rename("{$this->staticTypeOutputPath}/index.html", "$this->laravelTypeOutputPath/index.blade.php");

        // Move assets from public/docs to public/vendor/scribe
        // We need to do this delete first, otherwise move won't work if folder exists
        Utils::deleteDirectoryAndContents("public/vendor/scribe/", getcwd());
        rename("{$this->staticTypeOutputPath}/", "public/vendor/scribe/");

        $contents = file_get_contents("$this->laravelTypeOutputPath/index.blade.php");

        // Rewrite links to go through Laravel
        $contents = preg_replace('#href="css/(.+?)"#', 'href="{{ asset("vendor/scribe/css/$1") }}"', $contents);
        $contents = preg_replace('#src="(js|images)/(.+?)"#', 'src="{{ asset("vendor/scribe/$1/$2") }}"', $contents);
        $contents = str_replace('href="./collection.json"', 'href="{{ route("scribe.json") }}"', $contents);

        file_put_contents("$this->laravelTypeOutputPath/index.blade.php", $contents);
    }

    public function writeHtmlDocs(): void
    {
        ConsoleOutputUtils::info('Generating API HTML code');

        $this->pastel->generate($this->sourceOutputPath . '/index.md', $this->staticTypeOutputPath);

        if (!$this->isStatic) {
            $this->performFinalTasksForLaravelType();
        }

        ConsoleOutputUtils::success("Wrote HTML documentation to: " . ($this->isStatic ? $this->staticTypeOutputPath : $this->laravelTypeOutputPath));
    }

    protected function writeIndexMarkdownFile(array $settings): void
    {
        $indexMarkdownFile = $this->sourceOutputPath . '/index.md';
        if ($this->hasFileBeenModified($indexMarkdownFile)) {
            if ($this->shouldOverwrite) {
                ConsoleOutputUtils::warn("Discarding manual changes for file $indexMarkdownFile because you specified --force");
            } else {
                ConsoleOutputUtils::warn("Skipping modified file $indexMarkdownFile");
                return;
            }
        }

        $frontmatter = view('scribe::partials.frontmatter')
            ->with('showPostmanCollectionButton', $this->shouldGeneratePostmanCollection)
            // This path is wrong for laravel type but will be replaced in post
            ->with('postmanCollectionLink', './collection.json')
            ->with('outputPath', 'docs')
            ->with('settings', $settings);

        $introText = $this->config->get('intro_text', '');
        $introMarkdown = view('scribe::index')
            ->with('frontmatter', $frontmatter)
            ->with('introText', $introText);
        $this->writeFile($indexMarkdownFile, $introMarkdown);
    }

    protected function writeAuthMarkdownFile(): void
    {
        $authMarkdownFile = $this->sourceOutputPath . '/authentication.md';
        if ($this->hasFileBeenModified($authMarkdownFile)) {
            if ($this->shouldOverwrite) {
                ConsoleOutputUtils::warn("Discarding manual changes for file $authMarkdownFile because you specified --force");
            } else {
                ConsoleOutputUtils::warn("Skipping modified file $authMarkdownFile");
                return;
            }
        }

        $isAuthed = $this->config->get('auth.enabled', false);
        $text = '';
        $extraInfo = '';

        if ($isAuthed) {
            $strategy = $this->config->get('auth.in');
            $parameterName = $this->config->get('auth.name');
            $text = Arr::random([
                "This API is authenticated by sending ",
                "To authenticate requests, include ",
                "Authenticate requests to this API's endpoints by sending ",
            ]);
            switch ($strategy) {
                case 'query':
                    $text .= "a query parameter **`$parameterName`** in the request.";
                    break;
                case 'body':
                    $text .= "a parameter **`$parameterName`** in the body of the request.";
                    break;
                case 'query_or_body':
                    $text .= "a parameter **`$parameterName`** either in the query string or in the request body.";
                    break;
                case 'bearer':
                    $text .= "an **`Authorization`** header with the value **`\"Bearer {your-token}\"`**.";
                    break;
                case 'basic':
                    $text .= "an **`Authorization`** header in the form **`\"Basic {credentials}\"`**. The value of `{credentials}` should be your username/id and your password, joined with a colon (:), and then base64-encoded.";
                    break;
                case 'header':
                    $text .= "a **`$parameterName`** header with the value **`\"{your-token}\"`**.";
                    break;
            }
            $extraInfo = $this->config->get('auth.extra_info', '');
        }

        $authMarkdown = view('scribe::authentication', [
            'isAuthed' => $isAuthed,
            'authDescription' => $text,
            'extraAuthInfo' => $extraInfo,
        ]);
        $this->writeFile($authMarkdownFile, $authMarkdown);
    }

    protected function writeEndpointsMarkdownFile(Collection $parsedRoutes, array $settings): void
    {
        if (!is_dir($this->sourceOutputPath . '/groups')) {
            mkdir($this->sourceOutputPath . '/groups', 0777, true);
        }

        // Generate Markdown for each route. Not using a Blade component bc of some complex logic
        $parsedRoutesWithOutput = $this->generateMarkdownOutputForEachRoute($parsedRoutes, $settings);
        $groupFileNames = $parsedRoutesWithOutput->map(function ($routesInGroup, $groupName) {
            $groupId = Str::slug($groupName);
            $routeGroupMarkdownFile = $this->sourceOutputPath . "/groups/$groupId.md";

            if ($this->hasFileBeenModified($routeGroupMarkdownFile)) {
                if ($this->shouldOverwrite) {
                    ConsoleOutputUtils::warn("Discarding manual changes for file $routeGroupMarkdownFile because you specified --force");
                } else {
                    ConsoleOutputUtils::warn("Skipping modified file $routeGroupMarkdownFile");
                    return "$groupId.md";
                }
            }

            $groupDescription = Arr::first($routesInGroup, function ($route) {
                    return $route['metadata']['groupDescription'] !== '';
                })['metadata']['groupDescription'] ?? '';
            $groupMarkdown = view('scribe::partials.group')
                ->with('groupName', $groupName)
                ->with('groupDescription', $groupDescription)
                ->with('routes', $routesInGroup);

            $this->writeFile($routeGroupMarkdownFile, $groupMarkdown);
            return "$groupId.md";
        })->toArray();

        // Now, we need to delete any other Markdown files in the groups/ directory.
        // Why? Because, if we don't, if a user renames a group, the old file will still exist,
        // so the docs will have those endpoints repeated under the two groups.
        $filesInGroupFolder = scandir($this->sourceOutputPath . "/groups");
        $filesNotPresentInThisRun = collect($filesInGroupFolder)->filter(function ($fileName) use ($groupFileNames) {
            if (in_array($fileName, ['.', '..'])) {
                return false;
            }

           return !Str::is($groupFileNames, $fileName);
        });
        $filesNotPresentInThisRun->each(function ($fileName) {
            unlink($this->sourceOutputPath . "/groups/$fileName");
        });
    }

    /**
     */
    protected function writeFile(string $filePath, $markdown): void
    {
        file_put_contents($filePath, $markdown);
        $this->lastTimesWeModifiedTheseFiles[$filePath] = time();
    }

    /**
     */
    protected function writeModificationTimesTrackingFile(): void
    {
        $content = "# GENERATED. YOU SHOULDN'T MODIFY OR DELETE THIS FILE.\n";
        $content .= "# Scribe uses this file to know when you change something manually in your docs.\n";
        $content .= collect($this->lastTimesWeModifiedTheseFiles)
            ->map(function ($mtime, $filePath) {
                return "$filePath=$mtime";
            })->implode("\n");
        file_put_contents($this->fileModificationTimesFile, $content);
    }

    /**
     */
    protected function hasFileBeenModified(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $oldFileModificationTime = $this->lastTimesWeModifiedTheseFiles[$filePath] ?? null;

        if ($oldFileModificationTime) {
            $latestFileModifiedTime = filemtime($filePath);
            $wasFileModifiedManually = $latestFileModifiedTime > (int)$oldFileModificationTime;

            return $wasFileModifiedManually;
        }

        return false;
    }

    protected function fetchLastTimeWeModifiedFilesFromTrackingFile()
    {
        if (file_exists($this->fileModificationTimesFile)) {
            $lastTimesWeModifiedTheseFiles = explode("\n", trim(file_get_contents($this->fileModificationTimesFile)));
            // First two lines are comments
            array_shift($lastTimesWeModifiedTheseFiles);
            array_shift($lastTimesWeModifiedTheseFiles);
            $this->lastTimesWeModifiedTheseFiles = collect($lastTimesWeModifiedTheseFiles)
                ->mapWithKeys(function ($line) {
                    [$filePath, $modificationTime] = explode("=", $line);
                    return [$filePath => $modificationTime];
                })->toArray();
        }
    }

}
