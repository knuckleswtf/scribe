<?php


namespace Knuckles\Camel;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Knuckles\Camel\Output\EndpointData;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Symfony\Component\Yaml\Yaml;


class Camel
{
    /**
     * Load endpoints from the Camel files into groups (arrays).
     *
     * @param string $folder
     * @return array[]
     */
    public static function loadEndpointsIntoGroups(string $folder): array
    {
        $groups = [];
        self::loadEndpointsFromCamelFiles($folder, function ($group) use ($groups) {
            $group['endpoints'] = array_map(function (array $endpoint) {
                return new EndpointData($endpoint);
            }, $group['endpoints']);
            $groups[] = $group;
        });
        return $groups;
    }

    /**
     * Load endpoints from the Camel files into a flat list of endpoint arrays.
     *
     * @param string $folder
     * @return array[]
     */
    public static function loadEndpointsToFlatPrimitivesArray(string $folder): array
    {
        $endpoints = [];
        self::loadEndpointsFromCamelFiles($folder, function ($group) use ($endpoints) {
            foreach ($group['endpoints'] as $endpoint) {
                $endpoints[] = $endpoint;
            }
        });
        return $endpoints;
    }

    public static function loadEndpointsFromCamelFiles(string $folder, callable $callback)
    {
        $adapter = new Local(getcwd());
        $fs = new Filesystem($adapter);
        $contents = $fs->listContents($folder);;

        foreach ($contents as $object) {
            if ($object['type'] == 'file' && Str::endsWith($object['basename'], '.yaml')) {
                $group = Yaml::parseFile($object['path']);
                $callback($group);
            }
        }
    }

    public static function doesGroupContainEndpoint(array $group, EndpointData $endpoint): bool
    {
        return boolval(Arr::first($group['endpoints'], fn(EndpointData $e) => $e->endpointId() === $endpoint->endpointId()));
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
            'description' => Arr::first($group, function ($endpointData) {
                    return $endpointData->metadata->groupDescription !== '';
                })->metadata->groupDescription ?? '',
            'endpoints' => $group->map(fn($endpointData) => $endpointData->forOutput())->all(),
        ])->all();
    }
}