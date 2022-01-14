<?php


namespace Knuckles\Camel;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Tools\Utils;
use Symfony\Component\Yaml\Yaml;


class Camel
{
    /**
     * Mapping of group names to their generated file names. Helps us respect user reordering.
     * @var array<string, string>
     */
    public static array $groupFileNames = [];
    public static string $cacheDir = ".scribe/endpoints.cache";
    public static string $camelDir = ".scribe/endpoints";

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
        $contents = Utils::listDirectoryContents($folder);

        foreach ($contents as $object) {
            // Flysystem v1 had items as arrays; v2 has objects.
            // v2 allows ArrayAccess, but when we drop v1 support (Laravel <9), we should switch to methods
            if (
                $object['type'] == 'file'
                && Str::endsWith(basename($object['path']), '.yaml')
                && !Str::startsWith(basename($object['path']), 'custom.')
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
        $contents = Utils::listDirectoryContents($folder);

        $userDefinedEndpoints = [];
        foreach ($contents as $object) {
            // Flysystem v1 had items as arrays; v2 has objects.
            // v2 allows ArrayAccess, but when we drop v1 support (Laravel <9), we should switch to methods
            if (
                $object['type'] == 'file'
                && Str::endsWith(basename($object['path']), '.yaml')
                && Str::startsWith(basename($object['path']), 'custom.')
            ) {
                $endpoints = Yaml::parseFile($object['path']);
                foreach (($endpoints ?: []) as $endpoint) {
                    $userDefinedEndpoints[] = $endpoint;
                }
            }
        }

        return $userDefinedEndpoints;
    }

    public static function doesGroupContainEndpoint(array $group, OutputEndpointData $endpoint): bool
    {
        return boolval(Arr::first($group['endpoints'], function ($e) use ($endpoint) {
            return $e->endpointId() === $endpoint->endpointId();
        }));
    }

    public static function getEndpointIndexInGroup(array $groups, OutputEndpointData $endpoint): ?int
    {
        foreach ($groups as $group) {
            foreach ($group['endpoints'] as $index => $endpointInGroup) {
                if ($endpointInGroup->endpointId() === $endpoint->endpointId()) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * @param array[] $endpoints
     * @param array $endpointGroupIndexes Mapping of endpoint IDs to their index within their group
     *
     * @return array[]
     */
    public static function groupEndpoints(array $endpoints, array $endpointGroupIndexes): array
    {
        $groupedEndpoints = collect($endpoints)
            ->groupBy('metadata.groupName')
            ->sortKeys(SORT_NATURAL);

        return $groupedEndpoints->map(function (Collection $endpointsInGroup) use ($endpointGroupIndexes) {
            /** @var Collection<(int|string),ExtractedEndpointData> $endpointsInGroup */
            $sortedEndpoints = $endpointsInGroup;
            if (!empty($endpointGroupIndexes)) {
                $sortedEndpoints = $endpointsInGroup->sortBy(
                    fn(ExtractedEndpointData $e) => $endpointGroupIndexes[$e->endpointId()] ?? INF,
                );
            }

            return [
                'name' => Arr::first($endpointsInGroup, function (ExtractedEndpointData $endpointData) {
                        return !empty($endpointData->metadata->groupName);
                    })->metadata->groupName ?? '',
                'description' => Arr::first($endpointsInGroup, function (ExtractedEndpointData $endpointData) {
                        return !empty($endpointData->metadata->groupDescription);
                    })->metadata->groupDescription ?? '',
                'endpoints' => $sortedEndpoints->map(
                    fn(ExtractedEndpointData $endpointData) => $endpointData->forSerialisation()->toArray()
                )->values()->all(),
            ];
        })->values()->all();
    }

    public static function prepareGroupedEndpointsForOutput(array $groupedEndpoints): array
    {
        $groups = array_map(function (array $group) {
            return [
                'name' => $group['name'],
                'description' => $group['description'],
                'fileName' => self::$groupFileNames[$group['name']] ?? null,
                'endpoints' => array_map(function (array $endpoint) {
                    return OutputEndpointData::fromExtractedEndpointArray($endpoint);
                }, $group['endpoints']),
            ];
        }, $groupedEndpoints);
        return array_values(Arr::sort($groups, 'fileName'));
    }
}