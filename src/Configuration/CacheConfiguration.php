<?php

namespace Knuckles\Scribe\Configuration;

/**
 * Decouple scribe config file name from the cache directory.
 */
class CacheConfiguration
{
    /** @var string */
    private string $scribeConfig;

    /** @var string */
    private string $cacheDir;

    /** @var bool */
    private bool $isHidden;

    /**
     * @param string $cacheDir
     * @param string $scribeConfig
     * @param bool $isHidden
     */
    public function __construct(string $cacheDir, string$scribeConfig, bool $isHidden = true)
    {
        $this->cacheDir = $cacheDir;
        $this->scribeConfig = $scribeConfig;
        $this->isHidden = $isHidden;
    }

    /**
     * @return string
     */
    public function getScribeConfigFile(): string
    {
        return $this->scribeConfig;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return ($this->isHidden ? '.' : '') . $this->cacheDir;
    }
}
