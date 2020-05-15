<?php

namespace Knuckles\Scribe\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Knuckles\Scribe\Extracting\Generator;
use Knuckles\Scribe\Matching\Match;
use Knuckles\Scribe\Matching\RouteMatcherInterface;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Tools\ErrorHandlingUtils as e;
use Knuckles\Scribe\Tools\Flags;
use Knuckles\Scribe\Tools\Utils as u;
use Knuckles\Scribe\Writing\Writer;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use ReflectionClass;
use ReflectionException;

class GenerateDocumentation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "scribe:generate
                            {--force : Discard any changes you've made to the Markdown files}
                            {--no-extraction : Skip extraction of route info and just transform the Markdown files}
    ";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate API documentation from your Laravel/Dingo routes.';

    /**
     * @var DocumentationConfig
     */
    private $docConfig;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * Execute the console command.
     *
     * @param RouteMatcherInterface $routeMatcher
     *
     * @return void
     */
    public function handle(RouteMatcherInterface $routeMatcher)
    {
        $this->bootstrap();

        $noExtraction = $this->option('no-extraction');
        if ($noExtraction) {
            $writer = new Writer($this->docConfig);
            $writer->writeDocs();
            return;
        }

        $routes = $routeMatcher->getRoutes($this->docConfig->get('routes'), $this->docConfig->get('router'));

        $parsedRoutes = $this->processRoutes($routes);

        $groupedRoutes = collect($parsedRoutes)
            ->groupBy('metadata.groupName')
            ->sortBy(static function ($group) {
                /* @var $group Collection */
                return $group->first()['metadata']['groupName'];
            }, SORT_NATURAL);

        $writer = new Writer($this->docConfig, $this->option('force'));
        $writer->writeDocs($groupedRoutes);
    }

    /**
     * @param Match[] $matches
     *
     * @return array
     *@throws \ReflectionException
     *
     */
    private function processRoutes(array $matches)
    {
        $generator = new Generator($this->docConfig);
        $parsedRoutes = [];
        foreach ($matches as $routeItem) {
            /** @var Route $route */
            $route = $routeItem->getRoute();

            $routeControllerAndMethod = u::getRouteClassAndMethodNames($route);
            if (! $this->isValidRoute($routeControllerAndMethod)) {
                c::warn('Skipping invalid route: '. c::getRouteRepresentation($route));
                continue;
            }

            if (! $this->doesControllerMethodExist($routeControllerAndMethod)) {
                c::warn('Skipping route: '. c::getRouteRepresentation($route).' - Controller method does not exist.');
                continue;
            }

            if ($this->isRouteHiddenFromDocumentation($routeControllerAndMethod)) {
                c::warn('Skipping route: '. c::getRouteRepresentation($route). ': @hideFromAPIDocumentation was specified.');
                continue;
            }

            try {
                $parsedRoutes[] = $generator->processRoute($route, $routeItem->getRules());
                c::info('Processed route: '. c::getRouteRepresentation($route));
            } catch (\Exception $exception) {
                c::warn('Skipping route: '. c::getRouteRepresentation($route) . ' - Exception encountered.');
                e::dumpExceptionIfVerbose($exception);
            }
        }

        return $parsedRoutes;
    }

    /**
     * @param array $routeControllerAndMethod
     *
     * @return bool
     */
    private function isValidRoute(array $routeControllerAndMethod = null)
    {
        if (is_array($routeControllerAndMethod)) {
            [$classOrObject, $method] = $routeControllerAndMethod;
            if (u::isInvokableObject($classOrObject)) {
                return true;
            }
            $routeControllerAndMethod = $classOrObject . '@' . $method;
        }

        return ! is_callable($routeControllerAndMethod) && ! is_null($routeControllerAndMethod);
    }

    /**
     * @param array $routeControllerAndMethod
     *
     * @throws ReflectionException
     *
     * @return bool
     */
    private function doesControllerMethodExist(array $routeControllerAndMethod)
    {
        [$class, $method] = $routeControllerAndMethod;
        $reflection = new ReflectionClass($class);

        if ($reflection->hasMethod($method)) {
            return true;
        }

        return false;
    }

    /**
     * @param array $routeControllerAndMethod
     *
     * @throws ReflectionException
     *
     * @return bool
     */
    private function isRouteHiddenFromDocumentation(array $routeControllerAndMethod)
    {
        if (! ($class = $routeControllerAndMethod[0]) instanceof \Closure) {
            $classDocBlock = new DocBlock((new ReflectionClass($class))->getDocComment() ?: '');
            $shouldIgnoreClass = collect($classDocBlock->getTags())
                ->filter(function (Tag $tag) {
                    return Str::lower($tag->getName()) === 'hidefromapidocumentation';
                })->isNotEmpty();

            if ($shouldIgnoreClass) {
                return true;
            }
        }

        $methodDocBlock = new DocBlock(u::getReflectedRouteMethod($routeControllerAndMethod)->getDocComment() ?: '');
        $shouldIgnoreMethod = collect($methodDocBlock->getTags())
            ->filter(function (Tag $tag) {
                return Str::lower($tag->getName()) === 'hidefromapidocumentation';
            })->isNotEmpty();

        return $shouldIgnoreMethod;
    }

    public function bootstrap(): void
    {
        // Using a global static variable here, so 🙄 if you don't like it.
        // Also, the --verbose option is included with all Artisan commands.
        Flags::$shouldBeVerbose = $this->option('verbose');

        c::bootstrapOutput($this->output);

        $this->docConfig = new DocumentationConfig(config('scribe'));
        $this->baseUrl = $this->docConfig->get('base_url') ?? config('app.url');

        // Force root URL so it works in Postman collection
        URL::forceRootUrl($this->baseUrl);
    }
}
