<?php

namespace Knuckles\Scribe\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;
use Knuckles\Camel\Camel;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\GroupedEndpoints\GroupedEndpointsFactory;
use Knuckles\Scribe\Matching\RouteMatcherInterface;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Tools\Globals;
use Knuckles\Scribe\Writing\Writer;

class GenerateDocumentation extends Command
{
    protected $signature = "scribe:generate
                            {--force : Discard any changes you've made to the YAML or Markdown files}
                            {--no-extraction : Skip extraction of route and API info and just transform the YAML and Markdown files into HTML}
    ";

    protected $description = 'Generate API documentation from your Laravel/Dingo routes.';

    private DocumentationConfig $docConfig;

    private bool $shouldExtract;

    private bool $forcing;

    public function handle(RouteMatcherInterface $routeMatcher, GroupedEndpointsFactory $groupedEndpointsFactory): void
    {
        $this->bootstrap();

        $groupedEndpointsInstance = $groupedEndpointsFactory->make($this, $routeMatcher);

        $groupedEndpoints = $this->mergeUserDefinedEndpoints(
            $groupedEndpointsInstance->get(),
            Camel::loadUserDefinedEndpoints(Camel::$camelDir)
        );

        $writer = new Writer($this->docConfig);
        $writer->writeDocs($groupedEndpoints);

        if ($groupedEndpointsInstance->hasEncounteredErrors()) {
            c::warn('Generated docs, but encountered some errors while processing routes.');
            c::warn('Check the output above for details.');
        }
    }

    public function isForcing(): bool
    {
        return $this->forcing;
    }

    public function shouldExtract(): bool
    {
        return $this->shouldExtract;
    }

    public function getDocConfig(): DocumentationConfig
    {
        return $this->docConfig;
    }

    public function bootstrap(): void
    {
        // The --verbose option is included with all Artisan commands.
        Globals::$shouldBeVerbose = $this->option('verbose');

        c::bootstrapOutput($this->output);

        $this->docConfig = new DocumentationConfig(config('scribe'));

        // Force root URL so it works in Postman collection
        $baseUrl = $this->docConfig->get('base_url') ?? config('app.url');
        URL::forceRootUrl($baseUrl);

        $this->forcing = $this->option('force');
        $this->shouldExtract = !$this->option('no-extraction');

        if ($this->forcing && !$this->shouldExtract) {
            throw new \Exception("Can't use --force and --no-extraction together.");
        }

        // Reset this map useful for tests)
        Camel::$groupFileNames = [];
    }

    protected function mergeUserDefinedEndpoints(array $groupedEndpoints, array $userDefinedEndpoints): array
    {
        foreach ($userDefinedEndpoints as $endpoint) {
            $existingGroupKey = Arr::first(array_keys($groupedEndpoints), function ($key) use ($groupedEndpoints, $endpoint) {
                $group = $groupedEndpoints[$key];
                return $group['name'] === ($endpoint['metadata']['groupName'] ?? $this->docConfig->get('default_group', ''));
            });

            if ($existingGroupKey !== null) {
                $groupedEndpoints[$existingGroupKey]['endpoints'][] = OutputEndpointData::fromExtractedEndpointArray($endpoint);
            } else {
                $groupedEndpoints[] = [
                    'name' => $endpoint['metadata']['groupName'] ?? $this->docConfig->get('default_group', ''),
                    'description' => $endpoint['metadata']['groupDescription'] ?? null,
                    'endpoints' => [OutputEndpointData::fromExtractedEndpointArray($endpoint)],
                ];
            }
        }

        return $groupedEndpoints;
    }
}
