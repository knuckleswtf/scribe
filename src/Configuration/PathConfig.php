<?php

namespace Knuckles\Scribe\Configuration;

/**
 * A home for path configurations.
 */
class PathConfig
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
    public function getScribeConfigurationPath(string $resolvePath = null, string $separator = '/'): string
    {
        if (is_null($resolvePath)) {
            return $this->scribeConfig;
        }
        // Separate the path with a /
        return sprintf("%s%s%s", $this->scribeConfig, $separator, $resolvePath);
    }

    /**
     * @return string
     */
    public function getTemporaryDirectoryPath(string $resolvePath = null): string
    {
        $path = ($this->isHidden ? '.' : '') . $this->cacheDir;
        if (is_null($resolvePath)) {
            return $path;
        }
        // Separate the path with a /
        return sprintf("%s/%s", $path, $resolvePath);

    }
}
