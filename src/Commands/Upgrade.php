<?php

namespace Knuckles\Scribe\Commands;

use Illuminate\Console\Command;
use Shalvah\Upgrader\Upgrader;

class Upgrade extends Command
{
    protected $signature = "scribe:upgrade {--dry-run : Print the changes that will be made, without actually making them}
                            {--config=scribe : choose which config file to use}
                            ";

    protected $description = '';

    public function handle(): void
    {
        $configName = $this->option('config');
        if (!($oldConfig = config($configName))) {
            $this->error("The specified config (config/{$configName}.php) doesn't exist.");
            return;
        }

        if (array_key_exists("interactive", $oldConfig)) {
            $this->error("This upgrade tool is for upgrading from Scribe v3 to v4, but it looks like you're coming from v2.");
            $this->error("Please install v3 and follow its upgrade guide first.");
            return;
        }

        $this->info("Welcome to the Scribe v3 to v4 upgrader.");
        $this->line("Checking for config file changes...");

        $upgrader = Upgrader::ofConfigFile("config/$configName.php", __DIR__ . '/../../config/scribe.php')
            ->dontTouch('routes', 'laravel.middleware', 'postman.overrides', 'openapi.overrides',
                'example_languages', 'database_connections_to_transact', 'strategies')
            ->move('default_group', 'groups.default')
            ->move('faker_seed', 'examples.faker_seed');

        $changes = $upgrader->dryRun();
        if (empty($changes)) {
            $this->info("✔ No config file changes needed.");
        } else {
            $this->info('The following changes will be made to your config file:');
            $this->newLine();
            foreach ($changes as $change) {
                $this->line($change["description"]);
            }

            if (!($this->option('dry-run'))) {
                $upgrader->upgrade();
                $this->info("✔ Upgraded your config file. Your old config is backed up at config/$configName.php.bak.");
            }
        }
        $this->newLine();

        if ($this->confirm("Do you have any custom strategies?")) {
            $this->line('1. Add a new property <info>public ?ExtractedEndpointData $endpointData;</info>.');
            $this->line('2. Replace the <info>array $routeRules</info> parameter in __invoke() with <info>array $routeRules = []</info> .');
        }
        $this->newLine();

        if ($this->confirm("Did you customize the Blade templates used by Scribe?")) {
            $this->warn('A few minor changes were made to the templates. See the release announcement for details.');
        }
        $this->newLine();

        $this->line("Scribe now supports PHP 8 attributes for annotations. "
            . "You can use both, but we recommend switching to attributes (see the docs).");
        if ($this->confirm("Would you like help in replacing your docblock tags with attributes?")) {
            $this->warn('Install our Rector package knuckleswtf/scribe-rector-t2a and run it.');
        }
        $this->warn("For attributes to work, you need to add the attribute strategies to your config file. See the release announcement for details.");

        $this->newLine();
        $this->info("✔ Done.");
        $this->line("See the release announcement at <href=https://scribe.knuckles.wtf/v4>http://scribe.knuckles.wtf/v4</> for the full upgrade guide!");
    }

}
