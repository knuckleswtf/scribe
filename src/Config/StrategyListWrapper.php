<?php

namespace Knuckles\Scribe\Config;

use Illuminate\Support\Arr;

/**
 * @internal
 */
class StrategyListWrapper
{
    // Strategies can be:
    // 1. (Original) A class name, e.g. Strategies\Responses\ResponseCalls::class
    // 2. (New) A tuple containing the class name as item 1, and its config array as item 2
    // 3. (New) A tuple containing "override" as item 1, and the values to override array as item 2
    public function __construct(
        public array $strategies = []
    ) {

    }

    public function override(array $valuesToOverride): self
    {
        return $this->addStrategies(['override', $valuesToOverride]);
    }

    public function addStrategies(array|string ...$newStrategies): self
    {
        foreach ($newStrategies as $newStrategy) {
            $this->strategies[] = $newStrategy;
        }
        return $this;
    }

    public function removeStrategies(string ...$strategyNamesToRemove): self
    {
        $correspondingStrategies = Arr::where($this->strategies, function ($strategy) use ($strategyNamesToRemove) {
            $strategyName = is_string($strategy) ? $strategy : $strategy[0];
            return in_array($strategyName, $strategyNamesToRemove);
        });

        foreach ($correspondingStrategies as $key => $value) {
            unset($this->strategies[$key]);
        }

        return $this;
    }

    public function configure(array $configurationTuple): self
    {
        $this->strategies = array_map(function ($strategy) use ($configurationTuple) {
            $strategyName = is_string($strategy) ? $strategy : $strategy[0];
            return $strategyName == $configurationTuple[0] ? $configurationTuple : $strategy;
        }, $this->strategies);

        return $this;
    }

    public function toArray(): array
    {
        return $this->strategies;
    }
}
