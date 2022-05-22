<?php

namespace Knuckles\Scribe\Writing;

use Illuminate\Support\Facades\Storage;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Tools\Globals;
use Knuckles\Scribe\Tools\Utils;
use Symfony\Component\Yaml\Yaml;

class Writer
{
    private DocumentationConfig $config;

    private bool $isStatic;

    private string $markdownOutputPath;

    private string $staticTypeOutputPath;

    private string $laravelTypeOutputPath;
    protected array $generatedFiles = [
        'postman' => null,
        'openapi' => null,
        'html' => null,
        'blade' => null,
        'assets' => [
            'js' => null,
            'css' => null,
            'images' => null,
        ],
    ];

    private string $laravelAssetsPath;

    public function __construct(DocumentationConfig $config = null, $docsName = 'scribe')
    {
        $this->markdownOutputPath = ".{$docsName}"; //.scribe by default
        $this->laravelTypeOutputPath = "resources/views/$docsName";
        // If no config is injected, pull from global. Makes testing easier.
        $this->config = $config ?: new DocumentationConfig(config($docsName));

        $this->isStatic = $this->config->get('type') === 'static';
        $this->staticTypeOutputPath = rtrim($this->config->get('static.output_path', 'public/docs'), '/');

        $this->laravelAssetsPath = $this->config->get('laravel.assets_directory')
            ? '/' . $this->config->get('laravel.assets_directory')
            : '/vendor/scribe';
    }

    /**
     * @param array[] $groupedEndpoints
     */
    public function writeDocs(array $groupedEndpoints)
    {
        // The static assets (js/, css/, and images/) always go in public/docs/.
        // For 'static' docs, the output files (index.html, collection.json) go in public/docs/.
        // For 'laravel' docs, the output files (index.blade.php, collection.json)
        // go in resources/views/scribe/ and storage/app/scribe/ respectively.

        $this->writeHtmlDocs($groupedEndpoints);

        $this->writePostmanCollection($groupedEndpoints);

        $this->writeOpenAPISpec($groupedEndpoints);

        $this->runAfterGeneratingHook();
    }

    protected function writePostmanCollection(array $groups): void
    {
        if ($this->config->get('postman.enabled', true)) {
            c::info('Generating Postman collection');

            $collection = $this->generatePostmanCollection($groups);
            if ($this->isStatic) {
                $collectionPath = "{$this->staticTypeOutputPath}/collection.json";
                file_put_contents($collectionPath, $collection);
            } else {
                Storage::disk('local')->put('scribe/collection.json', $collection);
                $collectionPath = 'storage/app/scribe/collection.json';
            }

            c::success("Wrote Postman collection to: {$collectionPath}");
            $this->generatedFiles['postman'] = realpath($collectionPath);
        }
    }

    protected function writeOpenAPISpec(array $parsedRoutes): void
    {
        if ($this->config->get('openapi.enabled', false)) {
            c::info('Generating OpenAPI specification');

            $spec = $this->generateOpenAPISpec($parsedRoutes);
            if ($this->isStatic) {
                $specPath = "{$this->staticTypeOutputPath}/openapi.yaml";
                file_put_contents($specPath, $spec);
            } else {
                Storage::disk('local')->put('scribe/openapi.yaml', $spec);
                $specPath = 'storage/app/scribe/openapi.yaml';
            }

            c::success("Wrote OpenAPI specification to: {$specPath}");
            $this->generatedFiles['openapi'] = realpath($specPath);
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
        $writer = app()->makeWith(PostmanCollectionWriter::class, ['config' => $this->config]);

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
        $writer = app()->makeWith(OpenAPISpecWriter::class, ['config' => $this->config]);

        $spec = $writer->generateSpecContent($groupedEndpoints);
        $overrides = $this->config->get('openapi.overrides', []);
        if (count($overrides)) {
            foreach ($overrides as $key => $value) {
                data_set($spec, $key, $value);
            }
        }
        return Yaml::dump($spec, 20, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_OBJECT_AS_MAP);
    }

    protected function performFinalTasksForLaravelType(): void
    {
        if (!is_dir($this->laravelTypeOutputPath)) {
            mkdir($this->laravelTypeOutputPath, 0777, true);
        }
        $publicDirectory = app()->get('path.public');
        if (!is_dir($publicDirectory . $this->laravelAssetsPath)) {
            mkdir($publicDirectory . $this->laravelAssetsPath, 0777, true);
        }


        // Transform output HTML to a Blade view
        rename("{$this->staticTypeOutputPath}/index.html", "$this->laravelTypeOutputPath/index.blade.php");

        // Move assets from public/docs to public/vendor/scribe or config('laravel.assets_directory')
        // We need to do this delete first, otherwise move won't work if folder exists
        Utils::deleteDirectoryAndContents($publicDirectory . $this->laravelAssetsPath);
        rename("{$this->staticTypeOutputPath}/", $publicDirectory . $this->laravelAssetsPath);

        $contents = file_get_contents("$this->laravelTypeOutputPath/index.blade.php");

        // Rewrite asset links to go through Laravel
        $contents = preg_replace('#href="\.\./docs/css/(.+?)"#', 'href="{{ asset("' . $this->laravelAssetsPath . '/css/$1") }}"', $contents);
        $contents = preg_replace('#src="\.\./docs/(js|images)/(.+?)"#', 'src="{{ asset("' . $this->laravelAssetsPath . '/$1/$2") }}"', $contents);
        $contents = str_replace('href="../docs/collection.json"', 'href="{{ route("scribe.postman") }}"', $contents);
        $contents = str_replace('href="../docs/openapi.yaml"', 'href="{{ route("scribe.openapi") }}"', $contents);

        file_put_contents("$this->laravelTypeOutputPath/index.blade.php", $contents);
    }

    public function writeHtmlDocs(array $groupedEndpoints): void
    {
        c::info('Writing ' . ($this->isStatic ? 'HTML' : 'Blade') . ' docs...');

        // Then we convert them to HTML, and throw in the endpoints as well.
        /** @var HtmlWriter $writer */
        $writer = app()->makeWith(HtmlWriter::class, ['config' => $this->config]);
        $writer->generate($groupedEndpoints, $this->markdownOutputPath, $this->staticTypeOutputPath);

        if (!$this->isStatic) {
            $this->performFinalTasksForLaravelType();
        }

        if ($this->isStatic) {
            $outputPath = rtrim($this->staticTypeOutputPath, '/') . '/';
            c::success("Wrote HTML docs and assets to: $outputPath");
            $this->generatedFiles['html'] = realpath("{$outputPath}index.html");
            $assetsOutputPath = $outputPath;
        } else {
            $outputPath = rtrim($this->laravelTypeOutputPath, '/') . '/';
            c::success("Wrote Blade docs to: $outputPath");
            $this->generatedFiles['blade'] = realpath("{$outputPath}index.blade.php");
            $assetsOutputPath = app()->get('path.public') . $this->laravelAssetsPath;
            c::success("Wrote Laravel assets to: " . realpath($assetsOutputPath));
        }
        $this->generatedFiles['assets']['js'] = realpath("{$assetsOutputPath}js");
        $this->generatedFiles['assets']['css'] = realpath("{$assetsOutputPath}css");
        $this->generatedFiles['assets']['images'] = realpath("{$assetsOutputPath}images");
    }

    protected function runAfterGeneratingHook()
    {
        if (is_callable(Globals::$__afterGenerating)) {
            c::info("Running `afterGenerating()` hook...");
            call_user_func_array(Globals::$__afterGenerating, [$this->generatedFiles]);
        }
    }

}
