<?php

namespace Knuckles\Scribe\Extracting\Strategies\UrlParameters;

use Illuminate\Database\Eloquent\Model;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Illuminate\Support\Str;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\Utils;

class GetFromLaravelAPI extends Strategy
{
    use ParamHelpers;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules): ?array
    {
        if (Utils::isLumen()) {
            return null;
        }

        $parameters = [];

        $path = $endpointData->uri;
        preg_match_all('/\{(.*?)\}/', $path, $matches);

        foreach ($matches[1] as $match) {
            $optional = Str::endsWith($match, '?');
            $name = rtrim($match, '?');
            $type = 'string';
            $parameters[$name] = [
                'name' => $name,
                'description' => '',
                'required' => !$optional,
                'example' => $this->generateDummyValue($type),
                'type' => $type,
            ];
        }


        // Infer proper types for any bound models
        // Eg if $user model has an ID primary key and is injected, {user} param should be ID
        foreach ($endpointData->method->getParameters() as $param) {
            $paramType = $param->getType();
            try {
                $parameterClassName = $paramType->getName();
                $parameterInstance = new $parameterClassName;
                if ($parameterInstance instanceof Model) {
                    if (isset($parameters[$param->getName()])) {
                        $paramName = $param->getName();
                    } else if (isset($parameters['id'])) {
                        $paramName = 'id';
                    } else {
                        continue;
                    }

                    $type = $parameterInstance->getKeyType();
                    $parameters[$paramName]['type'] = $type;
                    $parameters[$paramName]['example'] = $this->generateDummyValue($this->normalizeTypeName($type));
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

            return $parameters;
        }
    }
