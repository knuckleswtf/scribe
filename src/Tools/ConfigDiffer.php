<?php

namespace Knuckles\Scribe\Tools;

use Illuminate\Support\Str;
use Symfony\Component\VarExporter\VarExporter;

class ConfigDiffer
{

    public function __construct(
        protected array $defaultConfig,
        protected array $usersConfig,
        protected array $ignorePaths = [],
        protected array $asList = [],
    )
    {
    }

    public function getDiff()
    {
        return $this->recursiveItemDiff($this->defaultConfig, $this->usersConfig);
    }

    protected function recursiveItemDiff($old, $new, $prefix = '')
    {
        $diff = [];

        foreach ($new as $key => $value) {
            $fullKey = $prefix.$key;
            if (Str::is($this->ignorePaths, $fullKey)) continue;

            $oldValue = data_get($old, $key);

            if (is_array($value)) {
                if (Str::is($this->asList, $fullKey)) {
                    $listDiff = $this->diffList($oldValue, $value);
                    if (!empty($listDiff)) {
                        $diff[$fullKey] = $listDiff;
                    }
                } else {
                    $diff = array_merge(
                        $diff, $this->recursiveItemDiff($oldValue, $value, "$fullKey.")
                    );
                }
            } else {
                if ($oldValue !== $value) {
                    $printedValue = json_encode($value, JSON_UNESCAPED_SLASHES);
                    $diff[$prefix.$key] = $printedValue;
                }
            }
        }

        return $diff;
    }

    protected function diffList(mixed $oldValue, array $value)
    {
        if (!is_array($oldValue)) {
            return "changed to a list";
        }

        $added = array_map(fn ($v) => "$v", $this->subtractArraysFlat($value, $oldValue));
        $removed = array_map(fn ($v) => "$v", $this->subtractArraysFlat($oldValue, $value));

        $diff = [];
        if (!empty($added)) {
            $diff[] = "added ".implode(", ", $added);
        }
        if (!empty($removed)) {
            $diff[] = "removed ".implode(", ", $removed);
        }

        return empty($diff) ? "" : implode(": ", $diff);
    }

    /**
     * Basically array_diff, but handling items which may also be arrays
     */
    protected function subtractArraysFlat(array $a, array $b)
    {
        $mapped_a = array_map(function ($item) {
            if (is_array($item)) {
                return VarExporter::export($item);
            }

            return $item;
        }, $a);
        $mapped_b = array_map(function ($item) {
            if (is_array($item)) {
                return VarExporter::export($item);
            }

            return $item;
        }, $b);

        return array_diff($mapped_a, $mapped_b);
    }
}
