<?php

namespace Knuckles\Scribe\Writing;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Pastel\Pastel;
use Knuckles\Scribe\Tools\ConsoleOutputUtils;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Tools\Globals;
use Knuckles\Scribe\Tools\Utils;
use Symfony\Component\Yaml\Yaml;

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
     * @var bool
     */
    private $shouldOverwrite;

    /**
     * @var bool
     */
    private $shouldGeneratePostmanCollection = false;

    /**
     * @var bool
     */
    private $shouldGenerateOpenAPISpec = false;

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
        $this->shouldOverwrite = $shouldOverwrite;
        $this->shouldGeneratePostmanCollection = $this->config->get('postman.enabled', false);
        $this->shouldGenerateOpenAPISpec = $this->config->get('openapi.enabled', false);
        $this->pastel = new Pastel();

        $this->isStatic = $this->config->get('type') === 'static';
        $this->staticTypeOutputPath = rtrim($this->config->get('static.output_path', 'public/docs'), '/');

        $this->fileModificationTimesFile = $this->sourceOutputPath . '/.filemtimes';
        $this->lastTimesWeModifiedTheseFiles = [];
    }

    /**
     * @param array[] $groups
     */
    public function writeDocs(array $groups)
    {
        // The source Markdown files always go in resources/docs.
        // The static assets (js/, css/, and images/) always go in public/docs/.
        // For 'static' docs, the output files (index.html, collection.json) go in public/docs/.
        // For 'laravel' docs, the output files (index.blade.php, collection.json)
        // go in resources/views/scribe/ and storage/app/scribe/ respectively.

        // When running with --no-extraction, $routes will be null.
        // In that case, we only want to write HTMl docs again, hence the conditionals below
        $this->writeMarkdownAndSourceFiles($groups);

        $this->writeHtmlDocs();

        $this->writePostmanCollection($groups);

        $this->writeOpenAPISpec($groups);
    }

    /**
     * @param array[] $groups
     *
     * @return void
     */
    public function writeMarkdownAndSourceFiles(array $groups): void
    {
        $settings = [
            'languages' => $this->config->get('example_languages'),
            'logo' => $this->config->get('logo'),
            'title' => $this->config->get('title') ?: config('app.name', '') . ' Documentation',
            'auth' => $this->config->get('auth'),
            'interactive' => $this->config->get('interactive', true)
        ];

        ConsoleOutputUtils::info('Writing source Markdown files to: ' . $this->sourceOutputPath);

        if (!is_dir($this->sourceOutputPath)) {
            mkdir($this->sourceOutputPath, 0777, true);
        }

        $this->fetchLastTimeWeModifiedFilesFromTrackingFile();

        $this->writeEndpointsMarkdownFile($groups, $settings);
        $this->writeIndexMarkdownFile($settings);
        $this->writeAuthMarkdownFile();

        $this->writeModificationTimesTrackingFile();

        ConsoleOutputUtils::info('Wrote source Markdown files to: ' . $this->sourceOutputPath);
    }

    /**
     * @param array[] $groups
     * @param array $settings
     *
     * @return array
     * @throws \Throwable
     */
    public function generateMarkdownOutputForEachRoute(array $groups, array $settings): array
    {
        $routesWithOutput = array_map(function ($group) use ($settings) {
            $group['endpoints'] = array_map(function (OutputEndpointData $endpointData) use ($settings) {
                $hasRequestOptions = !empty($endpointData->headers)
                    || !empty($endpointData->cleanQueryParameters)
                    || !empty($endpointData->cleanBodyParameters);
                // Needed for Try It Out
                $auth = $settings['auth'];
                if ($auth['in'] === 'bearer' || $auth['in'] === 'basic') {
                    $auth['name'] = 'Authorization';
                    $auth['location'] = 'header';
                    $auth['prefix'] = ucfirst($auth['in']).' ';
                } else {
                    $auth['location'] = $auth['in'];
                    $auth['prefix'] = '';
                }
                $endpointData->output = (string)view('scribe::partials.endpoint')
                    ->with('hasRequestOptions', $hasRequestOptions)
                    ->with('route', $endpointData->toArray())
                    ->with('endpointId', $endpointData->endpointId())
                    ->with('settings', $settings)
                    ->with('auth', $auth)
                    ->with('baseUrl', $this->baseUrl)
                    ->render();

                return $endpointData;
            }, $group['endpoints']);
            return $group;
        }, $groups);

        return $routesWithOutput;
    }

    protected function writePostmanCollection(array $groups): void
    {
        if ($this->shouldGeneratePostmanCollection) {
            ConsoleOutputUtils::info('Generating Postman collection');

            $collection = $this->generatePostmanCollection($groups);
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

    protected function writeOpenAPISpec(array $parsedRoutes): void
    {
        if ($this->shouldGenerateOpenAPISpec) {
            ConsoleOutputUtils::info('Generating OpenAPI specification');

            $spec = $this->generateOpenAPISpec($parsedRoutes);
            if ($this->isStatic) {
                $specPath = "{$this->staticTypeOutputPath}/openapi.yaml";
                file_put_contents($specPath, $spec);
            } else {
                Storage::disk('local')->put('scribe/openapi.yaml', $spec);
                $specPath = 'storage/app/scribe/openapi.yaml';
            }

            ConsoleOutputUtils::success("Wrote OpenAPI specification to: {$specPath}");
        }
    }

    /**
     * Generate Postman collection JSON file.
     *
     * @param array[] $groupedEndpoints
     *
     * @return string
     */
    public function generatePostmanCollection(array $groupedEndpoints): string
    {
        /** @var PostmanCollectionWriter $writer */
        $writer = app()->makeWith(
            PostmanCollectionWriter::class,
            ['config' => $this->config]
        );

        $collection = $writer->generatePostmanCollection($groupedEndpoints);
        $overrides = $this->config->get('postman.overrides', []);
        if (count($overrides)) {
            foreach ($overrides as $key => $value) {
                data_set($collection, $key, $value);
            }
        }
        return json_encode($collection, JSON_PRETTY_PRINT);
    }

    /**
     * @param array[] $groupedEndpoints
     *
     * @return string
     */
    public function generateOpenAPISpec(array $groupedEndpoints): string
    {
        /** @var OpenAPISpecWriter $writer */
        $writer = app()->makeWith(
            OpenAPISpecWriter::class,
            ['config' => $this->config]
        );

        $spec = $writer->generateSpecContent($groupedEndpoints);
        $overrides = $this->config->get('openapi.overrides', []);
        if (count($overrides)) {
            foreach ($overrides as $key => $value) {
                data_set($spec, $key, $value);
            }
        }
        return Yaml::dump($spec, 10, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_OBJECT_AS_MAP);
    }

    protected function performFinalTasksForLaravelType(): void
    {
        if (!is_dir($this->laravelTypeOutputPath)) {
            mkdir($this->laravelTypeOutputPath, 0777, true);
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
        $contents = str_replace('href="./collection.json"', 'href="{{ route("scribe.postman") }}"', $contents);
        $contents = str_replace('href="./openapi.yaml"', 'href="{{ route("scribe.openapi") }}"', $contents);

        file_put_contents("$this->laravelTypeOutputPath/index.blade.php", $contents);
    }

    public function writeHtmlDocs(): void
    {
        ConsoleOutputUtils::info('Transforming Markdown docs to HTML...');

        $this->pastel->generate($this->sourceOutputPath . '/index.md', $this->staticTypeOutputPath);
        // Add our custom JS
        copy(__DIR__.'/../../resources/js/tryitout.js', $this->staticTypeOutputPath . '/js/tryitout-'.Globals::SCRIBE_VERSION.'.js');

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
            ->with('showOpenAPISpecButton', $this->shouldGenerateOpenAPISpec)
            // These paths are wrong for laravel type but will be replaced in the performFinalTasksForLaravelType() method
            ->with('postmanCollectionLink', './collection.json')
            ->with('openAPISpecLink', './openapi.yaml')
            ->with('outputPath', 'docs')
            ->with('settings', $settings);

        $introText = $this->config->get('intro_text', '');
        $introMarkdown = view('scribe::index')
            ->with('frontmatter', $frontmatter)
            ->with('description', $this->config->get('description', ''))
            ->with('introText', $introText)
            ->with('baseUrl', $this->baseUrl)
            ->with('isInteractive', $this->config->get('interactive', true));
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
        $authDescription = '';
        $extraInfo = '';

        if ($isAuthed) {
            $strategy = $this->config->get('auth.in');
            $parameterName = $this->config->get('auth.name');
            $authDescription = Arr::random([
                "This API is authenticated by sending ",
                "To authenticate requests, include ",
                "Authenticate requests to this API's endpoints by sending ",
            ]);
            switch ($strategy) {
                case 'query':
                    $authDescription .= "a query parameter **`$parameterName`** in the request.";
                    break;
                case 'body':
                    $authDescription .= "a parameter **`$parameterName`** in the body of the request.";
                    break;
                case 'query_or_body':
                    $authDescription .= "a parameter **`$parameterName`** either in the query string or in the request body.";
                    break;
                case 'bearer':
                    $authDescription .= sprintf('an **`Authorization`** header with the value **`"Bearer %s"`**.', $this->config->get('auth.placeholder') ?: 'your-token');;
                    break;
                case 'basic':
                    $authDescription .= "an **`Authorization`** header in the form **`\"Basic {credentials}\"`**. The value of `{credentials}` should be your username/id and your password, joined with a colon (:), and then base64-encoded.";
                    break;
                case 'header':
                    $authDescription .= sprintf('a **`%s`** header with the value **`"%s"`**.', $parameterName, $this->config->get('auth.placeholder') ?: 'your-token');
                    break;
            }
            $authDescription .= "\n\nAll authenticated endpoints are marked with a `requires authentication` badge in the documentation below.";
            $extraInfo = $this->config->get('auth.extra_info', '');
        }

        $authMarkdown = view('scribe::authentication', [
            'isAuthed' => $isAuthed,
            'authDescription' => $authDescription,
            'extraAuthInfo' => $extraInfo,
        ]);
        $this->writeFile($authMarkdownFile, $authMarkdown);
    }

    /**
     * @param array[] $groups
     * @param array $settings
     */
    protected function writeEndpointsMarkdownFile(array $groups, array $settings): void
    {
        if (!is_dir($this->sourceOutputPath . '/groups')) {
            mkdir($this->sourceOutputPath . '/groups', 0777, true);
        }

        // Generate Markdown for each route. Not using a Blade component bc of some complex logic
        $groupsWithOutput = $this->generateMarkdownOutputForEachRoute($groups, $settings);
        $groupFileNames = array_map(function ($group) {
            $groupId = Str::slug($group['name']);
            $routeGroupMarkdownFile = $this->sourceOutputPath . "/groups/$groupId.md";

            if ($this->hasFileBeenModified($routeGroupMarkdownFile)) {
                if ($this->shouldOverwrite) {
                    ConsoleOutputUtils::warn("Discarding manual changes for file $routeGroupMarkdownFile because you specified --force");
                } else {
                    ConsoleOutputUtils::warn("Skipping modified file $routeGroupMarkdownFile");
                    return "$groupId.md";
                }
            }

            $groupMarkdown = view('scribe::partials.group')
                ->with('groupName', $group['name'])
                ->with('groupDescription', $group['description'])
                ->with('routes', $group['endpoints']);

            $this->writeFile($routeGroupMarkdownFile, $groupMarkdown);
            return "$groupId.md";
        }, $groupsWithOutput);

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
