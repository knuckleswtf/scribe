<?php

namespace Knuckles\Scribe\Commands;

use Illuminate\Console\Command;
use Knuckles\Camel\Camel;
use Knuckles\Scribe\GroupedEndpoints\GroupedEndpointsFactory;
use Knuckles\Scribe\Scribe;
use Knuckles\Scribe\Tools\PathConfig;
use Shalvah\Upgrader\Upgrader;
use Symfony\Component\VarExporter\VarExporter;

class Upgrade extends Command
{
    protected $signature = "scribe:upgrade {--dry-run : Print the changes that will be made, without actually making them}
                            {--config=scribe : choose which config file to use}
                            ";

    protected $description = '';
    protected bool $applyChanges;
    protected string $configName;

    public function handle(): void
    {
        $this->applyChanges = !$this->option('dry-run');

        $this->configName = $this->option('config');
        if (!($oldConfig = config($this->configName))) {
            $this->error("The specified config (config/{$this->configName}.php) doesn't exist.");
            return;
        }

        if (array_key_exists("interactive", $oldConfig)) {
            $this->error("This upgrade tool is for upgrading from Scribe v3 to v4, but it looks like you're coming from v2.");
            $this->error("Please install v3 and follow its upgrade guide first.");
            return;
        }

        $isMajorUpgrade = array_key_exists("default_group", $oldConfig) || array_key_exists("faker_seed", $oldConfig);

        if ($isMajorUpgrade) $this->info("Welcome to the Scribe v3 to v4 upgrader.");
        $this->line("Checking for config file changes...");

        $upgrader = Upgrader::ofConfigFile("config/$this->configName.php", __DIR__ . '/../../config/scribe.php')
            ->dontTouch('routes', 'laravel.middleware', 'postman.overrides', 'openapi.overrides',
                'example_languages', 'database_connections_to_transact', 'strategies', 'examples.models_source', 'external.html_attributes')
            ->move('default_group', 'groups.default')
            ->move('faker_seed', 'examples.faker_seed');

        if (!$isMajorUpgrade)
            $upgrader->dontTouch('groups');

        $changes = $upgrader->dryRun();
        if (empty($changes)) {
            $this->info("✔ No config file changes needed.");
        } else {
            $this->info('The following changes will be made to your config file:');
            $this->newLine();
            foreach ($changes as $change) {
                $this->line($change["description"]);
            }

            if ($this->applyChanges) {
                $upgrader->upgrade();
                $this->info("✔ Upgraded your config file. Your old config is backed up at config/$this->configName.php.bak.");
            }
        }
        $this->newLine();

        if (!$isMajorUpgrade) {
            $this->info("✔ Done.");
            $this->info(sprintf("See the full changelog at https://github.com/knuckleswtf/scribe/blob/%s/CHANGELOG.md", Scribe::VERSION));
            return;
        }

        $this->finishV4Upgrade();
    }

    protected function finishV4Upgrade(): void
    {
        $this->migrateToConfigFileSort();

        if ($this->applyChanges) {
            if ($this->confirm("Do you have any custom strategies?")) {
                $this->line('1. Add a new property <info>public ?ExtractedEndpointData $endpointData;</info>.');
                $this->line('2. Replace the <info>array $routeRules</info> parameter in __invoke() with <info>array $routeRules = []</info> .');
            }
            $this->newLine();
            $this->info("✔ Done.");
        }

        $this->line("See the release announcement at <href=https://scribe.knuckles.wtf/blog/laravel-v4>http://scribe.knuckles.wtf/blog/laravel-v4</> for the full upgrade guide!");
    }

    /**
     * In v3, you sorted endpoints by reordering them in the group file, and groups by renaming the group files alphabetically
     * (or by using `beforeGroup`/`afterGroup` for custom endpoints).
     * v4 replaces them with the config item `groups.order`.
     */
    protected function migrateToConfigFileSort()
    {
        $this->info("In v3, you sorted endpoints/groups by editing/renaming the generated YAML files (or `beforeGroup`/`afterGroup` for custom endpoints).");
        $this->info("We'll automatically import your current sorting into the config item `groups.order`.");

        $defaultGroup = config($this->configName.".default_group");
        $pathConfig = new PathConfig($this->configName);
        $extractedEndpoints = GroupedEndpointsFactory::fromCamelDir($pathConfig)->get();

        $order = array_map(function (array $group) {
            return array_map(function (array $endpoint) {
                return $endpoint['metadata']['title'] ?: ($endpoint['httpMethods'][0] . ' /'. $endpoint['uri']);
            }, $group['endpoints']);
        }, $extractedEndpoints);
        $groupsOrder = array_keys($order);
        $keyIndices = array_flip($groupsOrder);

        $userDefinedEndpoints = Camel::loadUserDefinedEndpoints(Camel::camelDir($pathConfig));

        if ($userDefinedEndpoints) {
            foreach ($userDefinedEndpoints as $endpoint) {
                $groupName = $endpoint['metadata']['groupName'] ?? $defaultGroup;
                $endpointTitle = $endpoint['metadata']['title'] ?? ($endpoint['httpMethods'][0] . ' /' . $endpoint['uri']);

                if (!isset($order[$groupName])) {
                    // This is a new group; place it at the right spot.
                    if (($nextGroup = $endpoint['metadata']['beforeGroup'] ?? null)) {
                        $index = $keyIndices[$nextGroup];
                        array_splice($groupsOrder, $index, 0, [$groupName]);
                    } else if (($previousGroup = $endpoint['metadata']['afterGroup'] ?? null)) {
                        $index = $keyIndices[$previousGroup];
                        array_splice($groupsOrder, $index + 1, 0, [$groupName]);
                    } else {
                        $groupsOrder[] = $groupName;
                    }
                    $order[$groupName] = [$endpointTitle];
                } else {
                    // Existing group, add endpoint
                    $order[$groupName] = [...$order[$groupName], $endpointTitle];
                }
            }
        }

        // Re-add them, so it's sorted in the right order
        $newOrder = [];
        foreach ($groupsOrder as $groupName) {
            $newOrder[$groupName] = $order[$groupName];
        }

        $output = VarExporter::export($newOrder);
        if ($this->applyChanges) {
            $configFile = "config/{$this->configName}.php";
            $output = str_replace("\n", "\n        ", $output);
            $newContents = str_replace(
                "'order' => [],",
                "'order' => $output,",
                file_get_contents($configFile)
            );
            file_put_contents($configFile, $newContents);
            $this->info("✔ Updated `groups.order`.");
        } else {
            $this->line("- `groups.order` will be set to:");
            $this->info($output);
        }
    }

}
