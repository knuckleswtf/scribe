<?php

namespace Knuckles\Scribe\Config;

use Illuminate\Support\Arr;

// Strategies can be:
// 1. (Original) A class name, e.g. Strategies\Responses\ResponseCalls::class
// 2. (New) A tuple containing the class name (or "static_data") as item 1, and its settings array as item 2

/**
 * Remove one or more strategies from a list of strategies.
 */
function removeStrategies(array $strategiesList, array $strategyNamesToRemove): array
{
    $correspondingStrategies = Arr::where($strategiesList, function ($strategy) use ($strategyNamesToRemove) {
        $strategyName = is_string($strategy) ? $strategy : $strategy[0];

        return in_array($strategyName, $strategyNamesToRemove);
    });

    foreach ($correspondingStrategies as $key => $value) {
        unset($strategiesList[$key]);
    }

    return $strategiesList;
}

/**
 * Add/replace a strategy and its settings in a list of strategies.
 * This method generates a tuple containing [strategyName, settingsArray],
 * and adds or replaces the strategy entry in the list.
 *
 * @param  array  $configurationTuple  Tuple of [strategyName, settingsArray].
 *                                     By default, all strategies support the "only" and "except" setting to apply them to specific endpoints.
 *                                     You can easily create the tuple by calling Strategy::wrapWithSettings(only: [], except: []).
 */
function configureStrategy(array $strategiesList, array $configurationTuple): array
{
    $strategyFound = false;
    $strategiesList = array_map(function ($strategy) use ($configurationTuple, &$strategyFound) {
        $strategyName = is_string($strategy) ? $strategy : $strategy[0];
        if ($strategyName === $configurationTuple[0]) {
            $strategyFound = true;

            return $configurationTuple;
        }

        return $strategy;
    }, $strategiesList);

    // If strategy wasn't in there, add it.
    if (! $strategyFound) {
        $strategiesList = array_merge($strategiesList, [$configurationTuple]);
    }

    return $strategiesList;
}
