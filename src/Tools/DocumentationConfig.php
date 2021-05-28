<?php

namespace Knuckles\Scribe\Tools;

use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;

class DocumentationConfig
{
    private $data;

    public function __construct(array $config = [])
    {
        $config['router'] = $this->getRouter($config);
        $this->data = $config;
    }

    /**
     * Get a config item with dot notation.
     * If the key does not exist, $default (or null) will be returned.
     *
     * @param string $key
     * @param mixed $default
     *
     * @return array|mixed
     */
    public function get(string $key, $default = null)
    {
        return data_get($this->data, $key, $default);
    }

    private function getRouter(array $config): string
    {
        if ($router = data_get($config, 'router', null)) {
            if (!in_array($router, ['dingo', 'laravel'])) {
                throw new \InvalidArgumentException("Unknown `router` config value: $router");
            }
            return $router;
        }

        if (class_exists(\Dingo\Api\Routing\Router::class)) {
            c::info('Detected Dingo API router');
            return 'dingo';
        }

        return 'laravel';

    }
}
