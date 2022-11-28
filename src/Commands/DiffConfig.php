<?php

namespace Knuckles\Scribe\Commands;

use Illuminate\Console\Command;
use Knuckles\Scribe\Tools\ConfigDiffer;

class DiffConfig extends Command
{
    protected $signature = "scribe:config:diff {--config=scribe : choose which config file to use}";

    protected $description = "Dump your changed config to the console. Use this when posting bug reports";

    public function handle(): void
    {
        $usersConfig = config($this->option('config'));
        $defaultConfig = require __DIR__."/../../config/scribe.php";

        $ignore = ['example_languages', 'routes', 'description', 'auth.extra_info', "intro_text", "groups"];
        $asList = ['strategies.*', "examples.models_source"];
        $differ = new ConfigDiffer($defaultConfig, $usersConfig, ignorePaths: $ignore, asList: $asList);

        $diff = $differ->getDiff();

        if (empty($diff)) {
            $this->info("------ SAME AS DEFAULT CONFIG ------");
            return;
        }

        foreach ($diff as $key => $item) {
            $this->line("$key => $item");
        }
    }

}
