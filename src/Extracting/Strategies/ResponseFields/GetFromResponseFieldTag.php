<?php

namespace Knuckles\Scribe\Extracting\Strategies\ResponseFields;

use Illuminate\Routing\Route;
use Knuckles\Scribe\Extracting\ParamHelpers;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use ReflectionClass;
use ReflectionFunctionAbstract;

class GetFromResponseFieldTag extends Strategy
{
    public $stage = 'responseFields';

    use ParamHelpers;

    public function __invoke(Route $route, ReflectionClass $controller, ReflectionFunctionAbstract $method, array $routeRules, array $alreadyExtractedData = [])
    {
        /** @var DocBlock $methodDocBlock */
        $methodDocBlock = RouteDocBlocker::getDocBlocksFromRoute($route)['method'];

        return $this->getResponseFieldsFromDocBlock($methodDocBlock->getTags(), $alreadyExtractedData['responses']);
    }

    /**
     * @param Tag[] $tags
     * @param array $responses
     *
     * @return array
     */
    public function getResponseFieldsFromDocBlock($tags, $responses)
    {
        $parameters = collect($tags)
            ->filter(function ($tag) {
                return $tag instanceof Tag && $tag->getName() === 'responseField';
            })
            ->mapWithKeys(function (Tag $tag) use ($responses) {
                // Format:
                // @responseField <name> <type> <description>
                // Examples:
                // @responseField text string The text.
                // @responseField user_id integer The ID of the user.
                preg_match('/(.+?)\s+(.+?)\s+([\s\S]*)/', $tag->getContent(), $content);
                if (empty($content)) {
                    // this means only name and type were supplied
                    [$name, $type] = preg_split('/\s+/', $tag->getContent());
                    $description = '';
                } else {
                    [$_, $name, $type, $description] = $content;
                    $description = trim($description);
                }

                $type = $this->normalizeParameterType($type);

                // Support optional type in annotation
                if (!$this->isSupportedTypeInDocBlocks($type)) {
                    // Then that wasn't a type, but part of the description
                    $description = "$type $description";

                    // Try to get a type from first 2xx response
                    $validResponse = collect($responses)->first(function ($r) {
                        $status = intval($r['status']);
                        return $status >= 200 && $status < 300;
                    });
                    $validResponse = json_decode($validResponse['content'] ?? null, true);
                    if (!$validResponse) {
                        $type = '';
                    } else {
                        $nonexistent = new \stdClass();
                        $value = $validResponse[$name]
                            ?? $validResponse['data'][$name] // Maybe it's a Laravel ApiResource
                            ?? $validResponse[0][$name] // Maybe it's a list
                            ?? $validResponse['data'][0][$name] // Maybe an Api Resource Collection?
                            ?? $nonexistent;
                        if ($value !== $nonexistent) {
                            $type =  $this->normalizeParameterType(gettype($value));
                        } else {
                            $type = '';
                        }
                    }
                }

                return [$name => compact('name', 'type', 'description')];
            })->toArray();

        return $parameters;
    }
}
