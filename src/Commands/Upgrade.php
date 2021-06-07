<?php

namespace Knuckles\Scribe\Commands;

use Illuminate\Console\Command;
use Knuckles\Scribe\Tools\Upgrader;

class Upgrade extends Command
{
    protected $signature = "scribe:upgrade {version=v3} {--dry-run}";

    protected $description = '';

    public function handle(): void
    {
        $toVersion = $this->argument('version');
        if ($toVersion !== 'v3') {
            return;
        }

        $oldConfig = config('scribe');
        $upgrader = Upgrader::ofConfigFile('config/scribe.php', __DIR__ . '/../../config/scribe.php')
            ->dontTouch('routes')
            ->move('interactive', 'try_it_out.enabled');

        if ($this->option('dry-run')) {
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
            return;
        }

        $upgrader->upgrade();

        if (!empty($oldConfig["continue_without_database_transactions"])) {
            $this->warn(
                '`continue_without_database_transactions` was deprecated in 2.4.0. Your new config file now uses `database_connections_to_transact`.'
                );
        }

        $this->newLine();
        $this->info("✔ Upgraded your config to $toVersion. Your old config is backed up at config/scribe.php.bak.");
        $this->info("Please review to catch any mistakes.");
        $this->warn("If you have any custom strategies or views, you should migrate those manually. See the migration guide at http://scribe.knuckles.wtf.");
        $this->info("Don't forget to check out the release announcement for new features!");
    }

}
