<?php

namespace Knuckles\Scribe\Extracting;

use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\ErrorHandlingUtils as e;
use Knuckles\Scribe\Tools\Utils;
use Throwable;

trait InstantiatesExampleModels
{
    /**
     * @param class-string $type
     * @param string[] $factoryStates
     * @param string[] $relations
     *
     * @return \Illuminate\Database\Eloquent\Model|object
     */
    protected function instantiateExampleModel(string $type, array $factoryStates = [], array $relations = [])
    {
        $configuredStrategies = $this->config->get('examples.models_source', ['factoryCreate', 'factoryMake', 'databaseFirst']);

        $strategies = [
            'factoryCreate' => fn() => $this->getExampleModelFromFactoryCreate($type, $factoryStates, $relations),
            'factoryMake' => fn() => $this->getExampleModelFromFactoryMake($type, $factoryStates, $relations),
            'databaseFirst' => fn() => $this->getExampleModelFromDatabaseFirst($type, $relations),
        ];

        foreach ($configuredStrategies as $strategyName) {
            try {
                $model = $strategies[$strategyName]();
                if ($model) return $model;
            } catch (Throwable $e) {
                c::warn("Couldn't get example model for {$type} via $strategyName.");
                e::dumpExceptionIfVerbose($e, true);
            }
        }

        return new $type;
    }

    /**
     * @param class-string $type
     * @param string[] $factoryStates
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function getExampleModelFromFactoryCreate(string $type, array $factoryStates = [], array $relations = [])
    {
        $factory = Utils::getModelFactory($type, $factoryStates, $relations);
        return $factory->create()->load($relations);
    }

    /**
     * @param class-string $type
     * @param string[] $factoryStates
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function getExampleModelFromFactoryMake(string $type, array $factoryStates = [], array $relations = [])
    {
        $factory = Utils::getModelFactory($type, $factoryStates, $relations);
        return $factory->make();
    }

    /**
     * @param class-string $type
     * @param string[] $relations
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function getExampleModelFromDatabaseFirst(string $type, array $relations = [])
    {
        return $type::with($relations)->first();
    }

}
