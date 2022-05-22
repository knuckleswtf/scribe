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

    private string $markdownOutputPath;

    private string $fileHashesTrackingFile;

    private array $lastKnownFileContentHashes = [];

    public function __construct(DocumentationConfig $config = null, bool $preserveUserChanges = true, string $docsName = 'scribe')
    {
        $this->markdownOutputPath = ".{$docsName}"; //.scribe by default
        // If no config is injected, pull from global. Makes testing easier.
        $this->config = $config ?: new DocumentationConfig(config($docsName));
        $this->baseUrl = $this->config->get('base_url') ?? config('app.url');
        $this->preserveUserChanges = $preserveUserChanges;

        $this->fileHashesTrackingFile = $this->markdownOutputPath . '/.filehashes';
        $this->lastKnownFileContentHashes = [];
    }

    public function writeMarkdownFiles(): void
    {
        ConsoleOutputUtils::info('Extracting intro and auth Markdown files to: ' . $this->markdownOutputPath);

        if (!is_dir($this->markdownOutputPath)) {
            mkdir($this->markdownOutputPath, 0777, true);
        }

        $this->fetchFileHashesFromTrackingFile();

        $this->writeIntroMarkdownFile();
        $this->writeAuthMarkdownFile();

        $this->writeContentsTrackingFile();

        ConsoleOutputUtils::success('Extracted intro and auth Markdown files to: ' . $this->markdownOutputPath);
    }

    public function writeIntroMarkdownFile(): void
    {
        $introMarkdownFile = $this->markdownOutputPath . '/intro.md';
        if ($this->hasFileBeenModified($introMarkdownFile)) {
            if ($this->preserveUserChanges) {
                ConsoleOutputUtils::warn("Skipping modified file $introMarkdownFile");
                return;
            }

            ConsoleOutputUtils::warn("Discarding manual changes for file $introMarkdownFile because you specified --force");
        }

        $introMarkdown = view('scribe::markdown.intro')
            ->with('description', $this->config->get('description', ''))
            ->with('introText', $this->config->get('intro_text', ''))
            ->with('baseUrl', $this->baseUrl)->render();
        $this->writeMarkdownFileAndRecordHash($introMarkdownFile, $introMarkdown);
    }

    public function writeAuthMarkdownFile(): void
    {
        $authMarkdownFile = $this->markdownOutputPath . '/auth.md';
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

        $authMarkdown = view('scribe::markdown.auth', [
            'isAuthed' => $isAuthed,
            'authDescription' => $authDescription,
            'extraAuthInfo' => $extraInfo,
        ])->render();
        $this->writeMarkdownFileAndRecordHash($authMarkdownFile, $authMarkdown);
    }

    /**
     */
    protected function writeMarkdownFileAndRecordHash(string $filePath, string $markdown): void
    {
        file_put_contents($filePath, $markdown);
        $this->lastKnownFileContentHashes[$filePath] = hash_file('md5', $filePath);
    }

    protected function writeContentsTrackingFile(): void
    {
        $content = "# GENERATED. YOU SHOULDN'T MODIFY OR DELETE THIS FILE.\n";
        $content .= "# Scribe uses this file to know when you change something manually in your docs.\n";
        $content .= collect($this->lastKnownFileContentHashes)
            ->map(fn($hash, $filePath) => "$filePath=$hash")->implode("\n");
        file_put_contents($this->fileHashesTrackingFile, $content);
    }

    protected function hasFileBeenModified(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $oldFileHash = $this->lastKnownFileContentHashes[$filePath] ?? null;

        if ($oldFileHash) {
            $currentFileHash = hash_file('md5', $filePath);
            // No danger of a timing attack, so no need for hash_equals() comparison
            $wasFileModifiedManually = $currentFileHash != $oldFileHash;

            return $wasFileModifiedManually;
        }

        return false;
    }

    protected function fetchFileHashesFromTrackingFile()
    {
        if (file_exists($this->fileHashesTrackingFile)) {
            $lastKnownFileHashes = explode("\n", trim(file_get_contents($this->fileHashesTrackingFile)));
            // First two lines are comments
            array_shift($lastKnownFileHashes);
            array_shift($lastKnownFileHashes);
            $this->lastKnownFileContentHashes = collect($lastKnownFileHashes)
                ->mapWithKeys(function ($line) {
                    [$filePath, $hash] = explode("=", $line);
                    return [$filePath => $hash];
                })->toArray();
        }
    }
}