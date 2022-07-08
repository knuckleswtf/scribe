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

    /**
     * @deprecated Use the cacheDir() method instead
     */
    public static string $cacheDir = ".scribe/endpoints.cache";
    /**
     * @deprecated Use the camelDir() method instead
     */
    public static string $camelDir = ".scribe/endpoints";

    public static function cacheDir(string $docsName = 'scribe')
    {
        return ".$docsName/endpoints.cache";
    }

    public static function camelDir(string $docsName = 'scribe')
    {
        return ".$docsName/endpoints";
    }

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
     * @param array $defaultGroupsOrder The order for groups that users specified in their config file.
     *
     * @return array[]
     */
    public static function groupEndpoints(array $endpoints, array $endpointGroupIndexes, array $defaultGroupsOrder = []): array
    {
        $groupedEndpoints = collect($endpoints)->groupBy('metadata.groupName');

        if ($defaultGroupsOrder) {
            $groupsOrder = Utils::getTopLevelItemsFromMixedConfigList($defaultGroupsOrder);
            $groupedEndpoints = $groupedEndpoints->sortKeysUsing(self::getOrderListComparator($groupsOrder));
        } else {
            $groupedEndpoints = $groupedEndpoints->sortKeys(SORT_NATURAL);
        }

        return $groupedEndpoints->map(function (Collection $endpointsInGroup) use ($defaultGroupsOrder, $endpointGroupIndexes) {
            /** @var Collection<(int|string),ExtractedEndpointData> $endpointsInGroup */
            $sortedEndpoints = $endpointsInGroup;
            if (empty($endpointGroupIndexes)) {
                $groupName = data_get($endpointsInGroup[0], 'metadata.groupName');
                if ($defaultGroupsOrder && isset($defaultGroupsOrder[$groupName])) {
                    $subGroupOrEndpointsOrder = Utils::getTopLevelItemsFromMixedConfigList($defaultGroupsOrder[$groupName]);
                    $sortedEndpoints = $endpointsInGroup->sortBy(
                        function (ExtractedEndpointData $e) use ($defaultGroupsOrder, $subGroupOrEndpointsOrder) {
                            $endpointIdentifier = $e->httpMethods[0].' /'.$e->uri;
                            $index = array_search($e->metadata->subgroup, $subGroupOrEndpointsOrder);

                            if ($index !== false) {
                                // This is a subgroup
                                $endpointsOrderInSubgroup = $defaultGroupsOrder[$e->metadata->groupName][$e->metadata->subgroup] ?? null;
                                if ($endpointsOrderInSubgroup) {
                                    $indexInSubGroup = array_search($endpointIdentifier, $endpointsOrderInSubgroup);
                                    $index = ($indexInSubGroup === false) ? $index : ($index + ($indexInSubGroup * 0.1));
                                }
                            } else {
                                // This is an endpoint
                                $index = array_search($endpointIdentifier, $subGroupOrEndpointsOrder);
                            }
                            return $index === false ? INF : $index;
                        },
                    );
                }
            } else {
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

    /**
     * Given an $order list like ['first', 'second', ...], return a compare function that can be used to sort
     * a list of strings based on the $order list. Any strings not in the list are sorted with natural sort.
     *
     * @param array $order
     */
    public static function getOrderListComparator(array $order): \Closure
    {
        return function ($a, $b) use ($order) {
            $indexOfA = array_search($a, $order);
            $indexOfB = array_search($b, $order);

            if ($indexOfA !== false && $indexOfB !== false) {
                return $indexOfA <=> $indexOfB;
            }

            // If only the first is in the default order, then it must come before the second.
            if ($indexOfA !== false) {
                return -1;
            }

            // If only the second is in the default order, then first must come after it.
            if ($indexOfB !== false) {
                return 1;
            }

            // If neither is present, fall back to natural sort
            return strnatcmp($a, $b);
        };
    }
}
