<?php

namespace Knuckles\Scribe\Extracting;

use Illuminate\Database\Eloquent\Model;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\ErrorHandlingUtils as e;
use Knuckles\Scribe\Tools\Utils;
use Throwable;

trait InstantiatesExampleModels
{
    /**
     * @param string $type
     *
     * @param array $relations
     * @param array $factoryStates
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

    protected function getExampleModelFromFactoryCreate(string $type, array $factoryStates = [], array $relations = [])
    {
        $factory = Utils::getModelFactory($type, $factoryStates, $relations);
        return $factory->create()->load($relations);
    }

    protected function getExampleModelFromFactoryMake(string $type, array $factoryStates = [], array $relations = [])
    {
        $factory = Utils::getModelFactory($type, $factoryStates, $relations);
        return $factory->make();
    }

    protected function getExampleModelFromDatabaseFirst(string $type, array $relations = [])
    {
        return $type::with($relations)->first();
    }

}
