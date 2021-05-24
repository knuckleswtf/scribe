<?php

namespace Knuckles\Scribe\Extracting;

use Illuminate\Support\Arr;
use Knuckles\Scribe\Tools\ConsoleOutputUtils;
use Knuckles\Scribe\Tools\DocumentationConfig;

/**
 * Handles extracting other API details — intro, auth
 */
class ApiDetails
{
    private DocumentationConfig $config;

    private string $baseUrl;

    private bool $preserveUserChanges;

    private string $markdownOutputPath = '.scribe';

    private string $fileModificationTimesFile;

    private array $lastTimesWeModifiedTheseFiles = [];

    public function __construct(DocumentationConfig $config = null, bool $preserveUserChanges = true)
    {
        // If no config is injected, pull from global. Makes testing easier.
        $this->config = $config ?: new DocumentationConfig(config('scribe'));
        $this->baseUrl = $this->config->get('base_url') ?? config('app.url');
        $this->preserveUserChanges = $preserveUserChanges;

        $this->fileModificationTimesFile = $this->markdownOutputPath . '/.filemtimes';
        $this->lastTimesWeModifiedTheseFiles = [];
    }

    public function writeMarkdownFiles(): void
    {
        ConsoleOutputUtils::info('Writing Markdown files to: ' . $this->markdownOutputPath);

        if (!is_dir($this->markdownOutputPath)) {
            mkdir($this->markdownOutputPath, 0777, true);
        }

        $this->fetchLastTimeWeModifiedFilesFromTrackingFile();

        $this->writeIndexMarkdownFile();
        $this->writeAuthMarkdownFile();

        $this->writeModificationTimesTrackingFile();

        ConsoleOutputUtils::info('Wrote Markdown files to: ' . $this->markdownOutputPath);
    }


    public function writeIndexMarkdownFile(): void
    {
        $indexMarkdownFile = $this->markdownOutputPath . '/index.md';
        if ($this->hasFileBeenModified($indexMarkdownFile)) {
            if ($this->preserveUserChanges) {
                ConsoleOutputUtils::warn("Skipping modified file $indexMarkdownFile");
                return;
            }

            ConsoleOutputUtils::warn("Discarding manual changes for file $indexMarkdownFile because you specified --force");
        }

        $introMarkdown = view('scribe::markdown.intro')
            ->with('description', $this->config->get('description', ''))
            ->with('introText', $this->config->get('intro_text', ''))
            ->with('baseUrl', $this->baseUrl)->render();
        $this->writeMarkdownFileAndRecordTime($indexMarkdownFile, $introMarkdown);
    }

    public function writeAuthMarkdownFile(): void
    {
        $authMarkdownFile = $this->markdownOutputPath . '/authentication.md';
        if ($this->hasFileBeenModified($authMarkdownFile)) {
            if ($this->preserveUserChanges) {
                ConsoleOutputUtils::warn("Skipping modified file $authMarkdownFile");
                return;
            }

            ConsoleOutputUtils::warn("Discarding manual changes for file $authMarkdownFile because you specified --force");
        }

        $isAuthed = $this->config->get('auth.enabled', false);
        $authDescription = '';
        $extraInfo = '';

        if ($isAuthed) {
            $strategy = $this->config->get('auth.in');
            $parameterName = $this->config->get('auth.name');
            $authDescription = Arr::random([
                "This API is authenticated by sending ",
                "To authenticate requests, include ",
                "Authenticate requests to this API's endpoints by sending ",
            ]);
            switch ($strategy) {
                case 'query':
                    $authDescription .= "a query parameter **`$parameterName`** in the request.";
                    break;
                case 'body':
                    $authDescription .= "a parameter **`$parameterName`** in the body of the request.";
                    break;
                case 'query_or_body':
                    $authDescription .= "a parameter **`$parameterName`** either in the query string or in the request body.";
                    break;
                case 'bearer':
                    $authDescription .= sprintf('an **`Authorization`** header with the value **`"Bearer %s"`**.', $this->config->get('auth.placeholder') ?: 'your-token');;
                    break;
                case 'basic':
                    $authDescription .= "an **`Authorization`** header in the form **`\"Basic {credentials}\"`**. The value of `{credentials}` should be your username/id and your password, joined with a colon (:), and then base64-encoded.";
                    break;
                case 'header':
                    $authDescription .= sprintf('a **`%s`** header with the value **`"%s"`**.', $parameterName, $this->config->get('auth.placeholder') ?: 'your-token');
                    break;
            }
            $authDescription .= "\n\nAll authenticated endpoints are marked with a `requires authentication` badge in the documentation below.";
            $extraInfo = $this->config->get('auth.extra_info', '');
        }

        $authMarkdown = view('scribe::markdown.authentication', [
            'isAuthed' => $isAuthed,
            'authDescription' => $authDescription,
            'extraAuthInfo' => $extraInfo,
        ])->render();
        $this->writeMarkdownFileAndRecordTime($authMarkdownFile, $authMarkdown);
    }

    /**
     */
    protected function writeMarkdownFileAndRecordTime(string $filePath, string $markdown): void
    {
        file_put_contents($filePath, $markdown);
        $this->lastTimesWeModifiedTheseFiles[$filePath] = time();
    }

    /**
     */
    protected function writeModificationTimesTrackingFile(): void
    {
        $content = "# GENERATED. YOU SHOULDN'T MODIFY OR DELETE THIS FILE.\n";
        $content .= "# Scribe uses this file to know when you change something manually in your docs.\n";
        $content .= collect($this->lastTimesWeModifiedTheseFiles)
            ->map(function ($mtime, $filePath) {
                return "$filePath=$mtime";
            })->implode("\n");
        file_put_contents($this->fileModificationTimesFile, $content);
    }

    /**
     */
    protected function hasFileBeenModified(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $oldFileModificationTime = $this->lastTimesWeModifiedTheseFiles[$filePath] ?? null;

        if ($oldFileModificationTime) {
            $latestFileModifiedTime = filemtime($filePath);
            $wasFileModifiedManually = $latestFileModifiedTime > (int)$oldFileModificationTime;

            return $wasFileModifiedManually;
        }

        return false;
    }

    protected function fetchLastTimeWeModifiedFilesFromTrackingFile()
    {
        if (file_exists($this->fileModificationTimesFile)) {
            $lastTimesWeModifiedTheseFiles = explode("\n", trim(file_get_contents($this->fileModificationTimesFile)));
            // First two lines are comments
            array_shift($lastTimesWeModifiedTheseFiles);
            array_shift($lastTimesWeModifiedTheseFiles);
            $this->lastTimesWeModifiedTheseFiles = collect($lastTimesWeModifiedTheseFiles)
                ->mapWithKeys(function ($line) {
                    [$filePath, $modificationTime] = explode("=", $line);
                    return [$filePath => $modificationTime];
                })->toArray();
        }
    }
}