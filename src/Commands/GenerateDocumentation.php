<?php

namespace Knuckles\Scribe\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;
use Knuckles\Camel\Camel;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Exceptions\GroupNotFound;
use Knuckles\Scribe\GroupedEndpoints\GroupedEndpointsFactory;
use Knuckles\Scribe\Matching\RouteMatcherInterface;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Tools\ErrorHandlingUtils as e;
use Knuckles\Scribe\Tools\Globals;
use Knuckles\Scribe\Writing\Writer;
use Shalvah\Upgrader\Upgrader;

class GenerateDocumentation extends Command
{
    protected $signature = "scribe:generate
                            {--force : Discard any changes you've made to the YAML or Markdown files}
                            {--no-extraction : Skip extraction of route and API info and just transform the YAML and Markdown files into HTML}
    ";

    protected $description = 'Generate API documentation from your Laravel/Dingo routes.';

    private DocumentationConfig $docConfig;

    private bool $shouldExtract;

    private bool $forcing;

    public function newLine($count = 1)
    {
        // TODO Remove when Laravel 6 is no longer supported
        $this->getOutput()->write(str_repeat("\n", $count));
        return $this;
    }

    public function handle(RouteMatcherInterface $routeMatcher, GroupedEndpointsFactory $groupedEndpointsFactory): void
    {
        $this->bootstrap();

        $groupedEndpointsInstance = $groupedEndpointsFactory->make($this, $routeMatcher);

        $userDefinedEndpoints = Camel::loadUserDefinedEndpoints(Camel::$camelDir);
        $groupedEndpoints = $this->mergeUserDefinedEndpoints(
            $groupedEndpointsInstance->get(),
            $userDefinedEndpoints
        );

        if (!count($userDefinedEndpoints)) {
            // Update the example custom file if there were no custom endpoints
            $this->writeExampleCustomEndpoint();
        }

        $writer = new Writer($this->docConfig);
        $writer->writeDocs($groupedEndpoints);

        if ($groupedEndpointsInstance->hasEncounteredErrors()) {
            c::warn('Generated docs, but encountered some errors while processing routes.');
            c::warn('Check the output above for details.');
        }

        $this->upgradeConfigFileIfNeeded();
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

    public function bootstrap(): void
    {
        // The --verbose option is included with all Artisan commands.
        Globals::$shouldBeVerbose = $this->option('verbose');

        c::bootstrapOutput($this->output);

        $this->docConfig = new DocumentationConfig(config('scribe'));

        // Force root URL so it works in Postman collection
        $baseUrl = $this->docConfig->get('base_url') ?? config('app.url');
        URL::forceRootUrl($baseUrl);

        $this->forcing = $this->option('force');
        $this->shouldExtract = !$this->option('no-extraction');

        if ($this->forcing && !$this->shouldExtract) {
            throw new \Exception("Can't use --force and --no-extraction together.");
        }

        // Reset this map (useful for tests)
        Camel::$groupFileNames = [];
    }

    protected function mergeUserDefinedEndpoints(array $groupedEndpoints, array $userDefinedEndpoints): array
    {
        foreach ($userDefinedEndpoints as $endpoint) {
            $indexOfGroupWhereThisEndpointShouldBeAdded = Arr::first(array_keys($groupedEndpoints), function ($key) use ($groupedEndpoints, $endpoint) {
                $group = $groupedEndpoints[$key];
                return $group['name'] === ($endpoint['metadata']['groupName'] ?? $this->docConfig->get('default_group', ''));
            });

            if ($indexOfGroupWhereThisEndpointShouldBeAdded !== null) {
                $groupedEndpoints[$indexOfGroupWhereThisEndpointShouldBeAdded]['endpoints'][] = OutputEndpointData::fromExtractedEndpointArray($endpoint);
            } else {
                $newGroup = [
                    'name' => $endpoint['metadata']['groupName'] ?? $this->docConfig->get('default_group', ''),
                    'description' => $endpoint['metadata']['groupDescription'] ?? null,
                    'endpoints' => [OutputEndpointData::fromExtractedEndpointArray($endpoint)],
                ];

                // Place the new group directly before/after an existing group
                // if `beforeGroup` or `afterGroup` was set.
                $beforeGroupName = $endpoint['metadata']['beforeGroup'] ?? null;
                $afterGroupName = $endpoint['metadata']['afterGroup'] ?? null;

                if ($beforeGroupName) {
                    $found = false;
                    $sortedGroupedEndpoints = [];
                    foreach ($groupedEndpoints as $group) {
                        if ($group['name'] === $beforeGroupName) {
                            $found = true;
                            $sortedGroupedEndpoints[] = $newGroup;
                        }
                        $sortedGroupedEndpoints[] = $group;
                    }

                    if (!$found) {
                        throw GroupNotFound::forTag($beforeGroupName, "beforeGroup:");
                    }
                    $groupedEndpoints = $sortedGroupedEndpoints;
                } else if ($afterGroupName) {
                    $found = false;
                    $sortedGroupedEndpoints = [];
                    foreach ($groupedEndpoints as $group) {
                        $sortedGroupedEndpoints[] = $group;
                        if ($group['name'] === $afterGroupName) {
                            $found = true;
                            $sortedGroupedEndpoints[] = $newGroup;
                        }
                    }

                    if (!$found) {
                        throw GroupNotFound::forTag($afterGroupName, "afterGroup:");
                    }
                    $groupedEndpoints = $sortedGroupedEndpoints;
                } else {
                    $groupedEndpoints[] = $newGroup;
                }
            }
        }

        return $groupedEndpoints;
    }

    protected function writeExampleCustomEndpoint(): void
    {
        // We add an example to guide users in case they need to add a custom endpoint.
        copy(__DIR__ . '/../../resources/example_custom_endpoint.yaml', Camel::$camelDir . '/custom.0.yaml');
    }

    protected function upgradeConfigFileIfNeeded(): void
    {
        $this->info("Checking for any pending upgrades to your config file...");
        try {
            $upgrader = Upgrader::ofConfigFile('config/scribe.php', __DIR__ . '/../../config/scribe.php')
                ->dontTouch(
                    'routes', 'example_languages', 'database_connections_to_transact', 'strategies', 'laravel.middleware',
                    'postman.overrides', 'openapi.overrides'
                );
            $changes = $upgrader->dryRun();
            if (!empty($changes)) {
                $this->newLine();

                $this->warn("You're using an updated version of Scribe, which added new items to the config file.");
                $this->info("Here are the changes:");
                foreach ($changes as $change) {
                    $this->info($change["description"]);
                }

                if (!$this->input->isInteractive()) {
                    $this->info("Run `php artisan scribe:upgrade` from an interactive terminal to update your config file automatically.");
                    $this->info(sprintf("Or see the full changelog at: https://github.com/knuckleswtf/scribe/blob/%s/CHANGELOG.md,", Globals::SCRIBE_VERSION));
                    return;
                }

                if ($this->confirm("Let's help you update your config file. Accept changes?")) {
                    $upgrader->upgrade();
                    $this->info(sprintf("✔ Updated. See the full changelog: https://github.com/knuckleswtf/scribe/blob/%s/CHANGELOG.md", Globals::SCRIBE_VERSION));
                }
            }
        } catch (\Throwable $e) {
            $this->warn("Check failed wih error:");
            e::dumpExceptionIfVerbose($e);
            $this->warn("This did not affect your docs. Please report this issue in the project repo: https://github.com/knuckleswtf/scribe");
        }

    }
}
