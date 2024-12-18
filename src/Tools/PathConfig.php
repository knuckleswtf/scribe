<?php

namespace Knuckles\Scribe\Tools;

/**
 * A home for path configurations. The important paths Scribe depends on.
 */
class PathConfig
{
    public function __construct(
        public string     $configName = 'scribe',
        // FOr lack of a better name, we'll call this `scribeDir`.
        // It's sort of the cache dir, where Scribe stores its intermediate outputs.
        protected ?string $scribeDir = null
    )
    {
        if (is_null($this->scribeDir)) {
            $this->scribeDir = ".{$this->configName}";
        }
    }

    public function outputPath(?string $resolvePath = null, string $separator = '/'): string
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
    public function intermediateOutputPath(?string $resolvePath = null, string $separator = '/'): string
    {
        if (is_null($resolvePath)) {
            return $this->scribeDir;
        }

        return "{$this->scribeDir}{$separator}{$resolvePath}";
    }
}
