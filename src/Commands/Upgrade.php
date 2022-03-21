<?php

namespace Knuckles\Scribe\Commands;

use Illuminate\Console\Command;
use Shalvah\Upgrader\Upgrader;

class Upgrade extends Command
{
    protected $signature = "scribe:upgrade {--dry-run}";

    protected $description = '';

    public function newLine($count = 1)
    {
        // TODO Remove when Laravel 6 is no longer supported
        $this->getOutput()->write(str_repeat("\n", $count));
        return $this;
    }

    public function handle(): void
    {
        $oldConfig = config('scribe');
        $upgrader = Upgrader::ofConfigFile('config/scribe.php', __DIR__ . '/../../config/scribe.php')
            ->dontTouch('routes', 'laravel.middleware', 'postman.overrides', 'openapi.overrides')
            ->move('interactive', 'try_it_out.enabled');

        $changes = $upgrader->dryRun();
        if (empty($changes)) {
            $this->info("No changes needed! Looks like you're all set.");
            return;
        }

        $this->info('The following changes will be made to your config file:');
        $this->newLine();
        foreach ($changes as $change) {
            $this->info($change["description"]);
        }

        if ($this->option('dry-run')) {
            return;
        }

        $upgrader->upgrade();

        if (!empty($oldConfig["continue_without_database_transactions"])) {
            $this->warn(
                '`continue_without_database_transactions` was deprecated in 2.4.0. Your new config file now uses `database_connections_to_transact`.'
                );
        }

        $this->newLine();
        $this->info("✔ Upgraded your config file. Your old config is backed up at config/scribe.php.bak.");
        $this->info("Don't forget to check out the changelog or release announcement for new features!");
    }

}
