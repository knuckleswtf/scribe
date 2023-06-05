<?php

namespace Knuckles\Scribe\Writing;

use Illuminate\Translation\FileLoader;
use Knuckles\Scribe\Tools\Globals;
use Illuminate\Contracts\Translation\Loader as LoaderContract;

class CustomTranslationsLoader extends FileLoader
{
    protected LoaderContract $defaultLoader;
    protected mixed $langPath;

    protected ?array $scribeTranslationsCache = null;
    protected ?array $userTranslationsCache = null;

    public function __construct(LoaderContract $loader)
    {
        $this->defaultLoader = $loader;
        $this->files = app('files');
        $this->langPath = app('path.lang');
    }

    public function load($locale, $group, $namespace = null)
    {
        // Laravel expects translation strings to be broken up into groups (files):
        // `lang/scribe/en/auth.php`, `lang/scribe/en/links.php`
        // We want to trick it into accepting a simple `lang/scribe.php`.

        if ($namespace == 'scribe') {
            if (isset($this->scribeTranslationsCache)) {
                $lines = $this->scribeTranslationsCache[$group] ?? [];
            } elseif ($this->files->exists($full = "{$this->hints[$namespace]}/scribe.php")) {
                $this->scribeTranslationsCache = $this->files->getRequire($full);
                $lines = $this->scribeTranslationsCache[$group] ?? [];
            } else {
                return [];
            }

            return $this->loadScribeNamespaceOverrides($lines, $locale, $group, $namespace);
        }

        return $this->defaultLoader->load($locale, $group, $namespace);
    }

    protected function loadScribeNamespaceOverrides(array $lines, $locale, $group, $namespace)
    {
        $userTranslationsFile = "{$this->langPath}/scribe.php";

        if ($this->files->exists($userTranslationsFile)) {
            if (!isset($this->userTranslationsCache)) {
                $this->userTranslationsCache = $this->files->getRequire($userTranslationsFile);
            }
            $userTranslations = $this->userTranslationsCache[$group] ?? [];
            return array_replace_recursive($lines, $userTranslations);
        }

        return $lines;
    }
}
