<?php

namespace Knuckles\Scribe\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Knuckles\Camel\Camel;
use Knuckles\Scribe\GroupedEndpoints\GroupedEndpointsFactory;
use Knuckles\Scribe\Matching\RouteMatcherInterface;
use Knuckles\Scribe\Scribe;
use Knuckles\Scribe\Tools\ConfigDiffer;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Tools\ErrorHandlingUtils as e;
use Knuckles\Scribe\Tools\Globals;
use Knuckles\Scribe\Tools\PathConfig;
use Knuckles\Scribe\Writing\Writer;

class GenerateDocumentation extends Command
{
    protected $signature = "scribe:generate
                            {--force : Discard any changes you've made to the YAML or Markdown files}
                            {--no-extraction : Skip extraction of route and API info and just transform the YAML and Markdown files into HTML}
                            {--no-upgrade-check : Skip checking for config file upgrades. Won't make things faster, but can be helpful if the command is buggy}
                            {--config=scribe : Choose which config file to use}
                            {--scribe-dir= : Specify the directory where Scribe stores its intermediate output and cache. Defaults to `.<config_file>`}
    ";

    protected $description = 'Generate API documentation from your Laravel routes.';

    protected DocumentationConfig $docConfig;

    protected bool $shouldExtract;

    protected bool $forcing;

    protected PathConfig $paths;

    public function handle(RouteMatcherInterface $routeMatcher, GroupedEndpointsFactory $groupedEndpointsFactory): void
    {
        $this->bootstrap();

        if (! empty($this->docConfig->get('default_group'))) {
            $this->warn('It looks like you just upgraded to Scribe v4.');
            $this->warn('Please run the upgrade command first: `php artisan scribe:upgrade`.');

            exit(1);
        }

        // Extraction stage - extract endpoint info either from app or existing Camel files (previously extracted data)
        $groupedEndpointsInstance = $groupedEndpointsFactory->make($this, $routeMatcher, $this->paths);
        $extractedEndpoints = $groupedEndpointsInstance->get();
        $userDefinedEndpoints = Camel::loadUserDefinedEndpoints(Camel::camelDir($this->paths));
        $groupedEndpoints = $this->mergeUserDefinedEndpoints($extractedEndpoints, $userDefinedEndpoints);

        // Output stage
        $configFileOrder = $this->docConfig->get('groups.order', []);
        $groupedEndpoints = Camel::prepareGroupedEndpointsForOutput($groupedEndpoints, $configFileOrder);

        if (! count($userDefinedEndpoints)) {
            // Update the example custom file if there were no custom endpoints
            $this->writeExampleCustomEndpoint();
        }

        /** @var Writer $writer */
        $writer = app(Writer::class, ['config' => $this->docConfig, 'paths' => $this->paths]);
        $writer->writeDocs($groupedEndpoints);

        // Retiring the automatic upgrade check, since the config file is no longer changing as frequently.
        // $this->upgradeConfigFileIfNeeded();

        $this->sayGoodbye(errored: $groupedEndpointsInstance->hasEncounteredErrors());
    }

    public function isForcing(): bool
    {
        return $this->forcing;
    }

    public function shouldExtract(): bool
    {
        return $this->shouldExtract;
    }

    public function getDocConfig(): DocumentationConfig
    {
        return $this->docConfig;
    }

    /**
     * Get access to output components for task-based output.
     *
     * @return Factory
     */
    public function outputComponents()
    {
        return $this->components;
    }

    public function bootstrap(): void
    {
        // The --verbose option is included with all Artisan commands.
        Globals::$shouldBeVerbose = $this->option('verbose');

        c::bootstrapOutput($this->output);
        c::setCommand($this);

        $configName = $this->option('config');
        if (! config($configName)) {
            throw new \InvalidArgumentException("The specified config (config/{$configName}.php) doesn't exist.");
        }

        $this->paths = new PathConfig($configName);
        if ($this->hasOption('scribe-dir') && ! empty($this->option('scribe-dir'))) {
            $this->paths = new PathConfig(
                $configName,
                scribeDir: $this->option('scribe-dir')
            );
        }

        $this->docConfig = new DocumentationConfig(config($this->paths->configName));

        // Force root URL so it works in Postman collection
        $baseUrl = $this->docConfig->get('base_url') ?? config('app.url');

        try {
            // Renamed from forceRootUrl in Laravel 11.43 or so
            URL::useOrigin($baseUrl);
        } catch (\BadMethodCallException) {
            URL::forceRootUrl($baseUrl);
        }

        $this->forcing = $this->option('force');
        $this->shouldExtract = ! $this->option('no-extraction');

        if ($this->forcing && ! $this->shouldExtract) {
            throw new \InvalidArgumentException("Can't use --force and --no-extraction together.");
        }

        $this->runBootstrapHook();
    }

    protected function runBootstrapHook()
    {
        if (is_callable(Globals::$__bootstrap)) {
            call_user_func_array(Globals::$__bootstrap, [$this]);
        }
    }

    protected function mergeUserDefinedEndpoints(array $groupedEndpoints, array $userDefinedEndpoints): array
    {
        foreach ($userDefinedEndpoints as $endpoint) {
            $indexOfGroupWhereThisEndpointShouldBeAdded = Arr::first(array_keys($groupedEndpoints), function ($key) use ($groupedEndpoints, $endpoint) {
                $group = $groupedEndpoints[$key];

                return $group['name'] === ($endpoint['metadata']['groupName'] ?? $this->docConfig->get('groups.default', ''));
            });

            if ($indexOfGroupWhereThisEndpointShouldBeAdded !== null) {
                $groupedEndpoints[$indexOfGroupWhereThisEndpointShouldBeAdded]['endpoints'][] = $endpoint;
            } else {
                $newGroup = [
                    'name' => $endpoint['metadata']['groupName'] ?? $this->docConfig->get('groups.default', ''),
                    'description' => $endpoint['metadata']['groupDescription'] ?? null,
                    'endpoints' => [$endpoint],
                ];

                $groupedEndpoints[$newGroup['name']] = $newGroup;
            }
        }

        return $groupedEndpoints;
    }

    protected function writeExampleCustomEndpoint(): void
    {
        // We add an example to guide users in case they need to add a custom endpoint.
        copy(__DIR__.'/../../resources/example_custom_endpoint.yaml', Camel::camelDir($this->paths).'/custom.0.yaml');
    }

    protected function upgradeConfigFileIfNeeded(): void
    {
        if ($this->option('no-upgrade-check')) {
            return;
        }

        $this->info('Checking for any pending upgrades to your config file...');

        try {
            $defaultConfig = require __DIR__.'/../../config/scribe.php';
            $ignore = ['example_languages', 'routes', 'description', 'auth.extra_info', 'intro_text', 'groups', 'database_connections_to_transact'];
            $asList = ['strategies.*', 'examples.models_source'];
            $differ = new ConfigDiffer(original: $this->docConfig->data, changed: $defaultConfig, ignorePaths: $ignore, asList: $asList);

            $diff = $differ->getDiff();
            // Remove items the user has set
            $realDiff = [];
            foreach ($diff as $key => $value) {
                if (is_null($this->docConfig->get($key))) {
                    $realDiff[$key] = $value;
                }
            }
            if (! empty($realDiff)) {
                $this->newLine();

                $this->warn("You're using an updated version of Scribe, which may have added new items to the config file.");
                $this->info("Here's what is different:");
                foreach ($realDiff as $key => $item) {
                    $this->line("{$key} --now defaults to-> {$item}");
                }

                if (! $this->input->isInteractive()) {
                    $this->info(sprintf('To upgrade, see the full changelog at: https://github.com/knuckleswtf/scribe/blob/%s/CHANGELOG.md,', Scribe::VERSION));
                    $this->info('And config reference at https://scribe.knuckles.wtf/laravel/reference/config');

                    return;
                }
            }
        } catch (\Throwable $e) {
            $this->warn('Check failed with error:');
            e::dumpExceptionIfVerbose($e);
            $this->warn('This did not affect your docs. Please report this issue in the project repo: https://github.com/knuckleswtf/scribe');
        }
    }

    protected function sayGoodbye(bool $errored = false): void
    {
        $message = 'All done.';
        $url = null;

        if ($this->docConfig->outputRoutedThroughLaravel()) {
            if ($this->docConfig->get('laravel.add_routes')) {
                $url = url($this->docConfig->get('laravel.docs_url'));
            }
        } elseif (Str::endsWith(base_path('public'), 'public') && Str::startsWith($this->docConfig->get('static.output_path'), 'public/')) {
            $url = url(str_replace('public/', '', $this->docConfig->get('static.output_path')));
        }

        $this->newLine();
        if ($url) {
            $this->components->twoColumnDetail($message, $url);
        } else {
            $this->components->info($message);
        }

        if ($errored) {
            $this->components->warn('Generated docs, but encountered some errors while processing routes.');
            $this->components->warn('Check the output above for details.');
            if (empty($_SERVER['SCRIBE_TESTS'])) {
                exit(2);
            }
        }
    }
}
