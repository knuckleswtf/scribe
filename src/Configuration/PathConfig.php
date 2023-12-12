<?php

namespace Knuckles\Scribe\Configuration;

/**
 * A home for path configurations. The important paths Scribe depends on.
 */
class PathConfig
{
    public function __construct(
        public string     $configName = 'scribe',
        protected ?string $cacheDir = null
    )
    {
        if (is_null($this->cacheDir)) {
            $this->cacheDir = ".{$this->configName}";
        }
    }

    public function outputPath(string $resolvePath = null, string $separator = '/'): string
    {
        if (is_null($resolvePath)) {
            return $this->configName;
        }

        return "{$this->configName}{$separator}{$resolvePath}";
    }

    public function configFileName(): string
    {
        return "{$this->configName}.php";
    }

    /**
     * The directory where Scribe writes its intermediate output (default is .<config> ie .scribe)
     */
    public function intermediateOutputPath(string $resolvePath = null, string $separator = '/'): string
    {
        if (is_null($resolvePath)) {
            return $this->cacheDir;
        }

        return "{$this->cacheDir}{$separator}{$resolvePath}";
    }
}
