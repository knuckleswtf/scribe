<?php

namespace Knuckles\Scribe\Tools;

use Illuminate\Support\Str;

class DocumentationConfig
{
    public array $data;

    public function __construct(array $config = [])
    {
        $this->data = $config;
    }

    /**
     * Get a config item with dot notation.
     * If the key does not exist, $default (or null) will be returned.
     *
     * @param  mixed  $default
     * @return array|mixed
     */
    public function get(string $key, $default = null)
    {
        return data_get($this->data, $key, $default);
    }

    public function outputIsStatic(): bool
    {
        return ! $this->outputRoutedThroughLaravel();
    }

    public function outputRoutedThroughLaravel(): bool
    {
        return Str::is(['laravel', 'external_laravel'], $this->get('type'));
    }

    public function outputIsExternal(): bool
    {
        return Str::is(['external_static', 'external_laravel'], $this->get('type'));
    }
}
