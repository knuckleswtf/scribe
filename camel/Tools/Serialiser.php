<?php


namespace Knuckles\Camel\Tools;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Knuckles\Camel\Extraction\EndpointData;


class Serialiser
{
    /**
     * @param EndpointData[] $endpoints
     */
    public static function serialiseEndpointsForOutput(array $endpoints): array
    {
        $groupedEndpoints = collect($endpoints)
            ->groupBy('metadata.groupName')
            ->sortBy(
                static fn(Collection $group) => $group->first()->metadata->groupName,
                SORT_NATURAL
            );

        return $groupedEndpoints->map(fn(Collection $group) => [
            'name' => $group[0]->metadata->groupName,
            'description' => Arr::first($group, function (EndpointData $endpointData) {
                    return $endpointData->metadata->groupDescription !== '';
                })->metadata->groupDescription ?? '',
            'endpoints' => $group->map(fn(EndpointData $endpointData) => $endpointData->forOutput())
                ->toArray(),
        ])->toArray();
    }
}