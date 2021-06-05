<?php


namespace Knuckles\Camel;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Camel\Output\OutputEndpointData;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Symfony\Component\Yaml\Yaml;


class Camel
{
    /**
     * Mapping of group names to their generated file names. Helps us respect user reordering.
     * @var array<string, string>
     */
    public static array $groupFileNames = [];

    /**
     * Load endpoints from the Camel files into groups (arrays).
     *
     * @param string $folder
     *
     * @return array[]
     */
    public static function loadEndpointsIntoGroups(string $folder): array
    {
        $groups = [];
        self::loadEndpointsFromCamelFiles($folder, function ($group) use (&$groups) {
            $group['endpoints'] = array_map(function (array $endpoint) {
                return OutputEndpointData::fromExtractedEndpointArray($endpoint);
            }, $group['endpoints']);
            $groups[] = $group;
        });
        return $groups;
    }

    /**
     * Load endpoints from the Camel files into a flat list of endpoint arrays.
     *
     * @param string $folder
     *
     * @return array[]
     */
    public static function loadEndpointsToFlatPrimitivesArray(string $folder, bool $isFromCache = false): array
    {
        $endpoints = [];
        self::loadEndpointsFromCamelFiles($folder, function ($group) use (&$endpoints) {
            foreach ($group['endpoints'] as $endpoint) {
                $endpoints[] = $endpoint;
            }
        }, !$isFromCache);
        return $endpoints;
    }

    public static function loadEndpointsFromCamelFiles(string $folder, callable $callback, bool $storeGroupFilePaths = true)
    {
        $adapter = new Local(getcwd());
        $fs = new Filesystem($adapter);
        $contents = $fs->listContents($folder);;

        foreach ($contents as $object) {
            if (
                $object['type'] == 'file'
                && Str::endsWith($object['basename'], '.yaml')
                && !Str::startsWith($object['basename'], 'custom.')
            ) {
                $group = Yaml::parseFile($object['path']);
                if ($storeGroupFilePaths) {
                    $filePathParts = explode('/', $object['path']);
                    self::$groupFileNames[$group['name']] = end($filePathParts);
                }
                $callback($group);
            }
        }
    }

    public static function loadUserDefinedEndpoints(string $folder): array
    {
        $adapter = new Local(getcwd());
        $fs = new Filesystem($adapter);
        $contents = $fs->listContents($folder);;

        $userDefinedEndpoints = [];
        foreach ($contents as $object) {
            if (
                $object['type'] == 'file'
                && Str::endsWith($object['basename'], '.yaml')
                && Str::startsWith($object['basename'], 'custom.')
            ) {
                $endpoints = Yaml::parseFile($object['path']);
                foreach (($endpoints ?: []) as $endpoint) {
                    $userDefinedEndpoints[] = $endpoint;
                }
            }
        }

        return $userDefinedEndpoints;
    }

    public static function doesGroupContainEndpoint(array $group, $endpoint): bool
    {
        return boolval(Arr::first($group['endpoints'], function ($e) use ($endpoint) {
            return $e->endpointId() === $endpoint->endpointId();
        }));
    }

    /**
     * @param array[] $endpoints
     *
     * @return array[]
     */
    public static function groupEndpoints(array $endpoints): array
    {
        $groupedEndpoints = collect($endpoints)
            ->groupBy('metadata.groupName')
            ->sortKeys(SORT_NATURAL);

        return $groupedEndpoints->map(fn(Collection $group) => [
            'name' => $group[0]->metadata->groupName,
            'description' => Arr::first($group, function (ExtractedEndpointData $endpointData) {
                    return $endpointData->metadata->groupDescription !== '';
                })->metadata->groupDescription ?? '',
            'endpoints' => $group->map(fn(ExtractedEndpointData $endpointData) => $endpointData->forSerialisation()->toArray())->all(),
        ])->values()->all();
    }

    public static function prepareGroupedEndpointsForOutput(array $groupedEndpoints): array
    {
        $groups =  array_map(function (array $group) {
            return [
                'name' => $group['name'],
                'description' => $group['description'],
                'fileName' => self::$groupFileNames[$group['name']] ?? null,
                'endpoints' => array_map(function (array $endpoint) {
                    return OutputEndpointData::fromExtractedEndpointArray($endpoint);
                }, $group['endpoints']),
            ];
        }, $groupedEndpoints);
        return Arr::sort($groups, 'fileName');
    }
}