<?php

namespace Knuckles\Scribe\Extracting;

use Illuminate\Support\Arr;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\ErrorHandlingUtils as e;
use Knuckles\Scribe\Tools\Utils;
use ReflectionFunctionAbstract;
use Throwable;

trait InstantiatesExampleModels
{
    /**
     * @param string|null $type
     * @param string[] $factoryStates
     * @param string[] $relations
     * @param \ReflectionFunctionAbstract|null $transformationMethod A method which has the model as its first parameter. Useful if the `$type` is empty.
     *
     * @return \Illuminate\Database\Eloquent\Model|object|null
     */
    protected function instantiateExampleModel(
        ?string $type = null, array $factoryStates = [],
        array   $relations = [], ?ReflectionFunctionAbstract $transformationMethod = null
    )
    {
        // If the API Resource uses an empty resource, there won't be an example model
        if($type == null && $transformationMethod == null)
            return null;

        if ($type == null) {
            $parameter = Arr::first($transformationMethod->getParameters());
            if ($parameter->hasType() && !$parameter->getType()->isBuiltin() && class_exists($parameter->getType()->getName())) {
                // Ladies and gentlemen, we have a type!
                $type = $parameter->getType()->getName();
            }
        }
        if ($type == null) {
            throw new \Exception("Couldn't detect a transformer model from your doc block. Did you remember to specify a model using @transformerModel?");
        }

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
