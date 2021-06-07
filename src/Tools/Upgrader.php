<?php


namespace Knuckles\Scribe\Tools;


use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use PhpParser;
use PhpParser\{Node, NodeFinder, Lexer, NodeTraverser, NodeVisitor, Parser, ParserFactory, PrettyPrinter};

class Upgrader
{
    public const CHANGE_REMOVED = 'removed';
    public const CHANGE_RENAMED = 'renamed';
    public const CHANGE_ADDED = 'added';
    public const CHANGE_ARRAY_ITEM_ADDED = 'added_to_array';

    private array $configFiles = [];
    private array $movedKeys = [];
    private array $dontTouchKeys = [];
    private array $userFacingChanges = [];
    private array $catchUps = [];

    /** @var Node\Stmt[] */
    private ?array $outgoingConfigFileAst = [];
    /** @var Node\Stmt[] */
    private array $incomingConfigFileAstForModification = [];
    /** @var Node\Stmt[]|null */
    private ?array $incomingConfigFileOriginalAst;
    private array $incomingConfigFileOriginalTokens;

    public function __construct(string $userRelativeLocation, string $packageAbsolutePath)
    {
        $this->configFiles['user_relative'] = $userRelativeLocation;
        $this->configFiles['package_absolute'] = $packageAbsolutePath;
    }

    public static function ofConfigFile(string $userRelativeLocation, string $packageAbsolutePath): self
    {
        return new self($userRelativeLocation, $packageAbsolutePath);
    }

    public function upgrade()
    {
        $this->fetchUserFacingChanges();
        $this->applyChanges();
    }

    public function dryRun(): array
    {
        $this->fetchUserFacingChanges(true);
        return $this->userFacingChanges;
    }

    protected function fetchUserFacingChanges($forDisplay = false)
    {
        $userCurrentConfig = require(getcwd() . '/' . ltrim($this->configFiles['user_relative'], '/'));
        $incomingConfig = require $this->configFiles['package_absolute'];

        $forDisplay && $this->fetchAddedItems($userCurrentConfig, $incomingConfig);
        $this->fetchRemovedOrRenamedItems($userCurrentConfig, $incomingConfig);
    }

    protected function fetchRemovedOrRenamedItems(array $userCurrentConfig, $incomingConfig, string $rootKey = '')
    {

        if (is_array($incomingConfig)) {
            $arrayKeys = array_keys($incomingConfig);
            if (($arrayKeys[0] ?? null) === 0) {
                // We're dealing with a list of items (numeric array); will be handled by the method that fetches added items
                // Here, we'll just get any extra items the user added

                $outgoing = $this->getOutgoingConfigItem($rootKey);
                $incoming = $this->getIncomingConfigItem($rootKey);

                foreach ($outgoing->items as $i => $outgoingItem) {
                    if ($outgoingItem->value instanceof Node\Scalar
                        && $incoming->items[0]->value instanceof Node\Scalar) {
                        $inIncoming = Arr::first(
                            $incoming->items,
                            fn(Node\Expr\ArrayItem $incomingItem) => $incomingItem->value->value === $outgoingItem->value->value
                        );
                        if (!$inIncoming) {
                            $this->catchUps[$rootKey . ".$i"] = $outgoingItem;
                        }
                    } else if ($outgoingItem->value instanceof Node\Expr\ClassConstFetch
                        && $incoming->items[0]->value instanceof Node\Expr\ClassConstFetch) {
                        // Handle ::class statements
                        $inIncoming = Arr::first(
                            $incoming->items,
                            function (Node\Expr\ArrayItem $incomingItem) use ($outgoingItem) {
                                // Rough equality check using final segments of class name
                                $classNamePartsReversed = array_reverse($outgoingItem->value->class->parts);
                                foreach ($classNamePartsReversed as $i => $classNamePart) {
                                    $incomingClassNamePartsReversed = array_reverse($incomingItem->value->class->parts);
                                    if (isset($incomingClassNamePartsReversed[$i])
                                    && $incomingClassNamePartsReversed[$i] === $classNamePart) {
                                        return true;
                                    }
                                }
                            }
                        );
                        if (!$inIncoming) {
                            $this->catchUps[$rootKey . ".$i"] = $outgoingItem;
                        }
                    } else {
                        $this->catchUps[$rootKey . ".$i"] = $outgoingItem;
                    }
                }
                return;
            }
        }

        foreach ($userCurrentConfig as $key => $value) {
            $fullKey = $this->getFullKey($key, $rootKey);

            $outgoing = $this->getOutgoingConfigItem($fullKey);

            if ($this->wasKeyMoved($fullKey)) {
                $this->userFacingChanges[] = [
                    'type' => self::CHANGE_RENAMED,
                    'key' => $fullKey,
                    'new_key' => $this->movedKeys[$fullKey],
                    'new_value' => $outgoing,
                    'description' => "- `$fullKey` will be moved to `{$this->movedKeys[$fullKey]}`.",
                ];
                continue;
            }

            if (!array_key_exists($key, $incomingConfig)) {
                $this->userFacingChanges[] = [
                    'type' => self::CHANGE_REMOVED,
                    'key' => $fullKey,
                    'description' => "- `$fullKey` will be removed.",
                ];
                continue;
            }

            if ($this->canModifyKey($fullKey) && is_array($value)) {
                // Recurse into the array
                $this->fetchRemovedOrRenamedItems($value, data_get($incomingConfig, $key), $fullKey);
            } else {
                // This key is present in both existing and incoming configs
                // Save the user's value so we can replace the default in the incoming
                $this->catchUps[$fullKey] = $outgoing;
            }

        }
    }

    /**
     * Report the new items in the incoming config
     */
    protected function fetchAddedItems(array $userCurrentConfig, array $incomingConfig, string $rootKey = '')
    {
        if (is_array($incomingConfig)) {
            $arrayKeys = array_keys($incomingConfig);
            if (($arrayKeys[0] ?? null) === 0) {
                // We're dealing with a list of items (numeric array)
                $diff = array_diff($incomingConfig, $userCurrentConfig);
                if (!empty($diff)) {
                    foreach ($diff as $item) {
                        $this->userFacingChanges[] = [
                            'type' => self::CHANGE_ARRAY_ITEM_ADDED,
                            'key' => $rootKey,
                            'value' => $item,
                            'description' => "- '$item' will be added to `$rootKey`.",
                        ];
                    }
                }
                return;
            }
        }

        foreach ($incomingConfig as $key => $value) {
            $fullKey = $this->getFullKey($key, $rootKey);

            if (!$this->canModifyKey($fullKey)) {
                continue;
            }

            if (Arr::exists($userCurrentConfig, $key)) {
                if (is_array($value)) {
                    // Recurse into array
                    $this->fetchAddedItems(data_get($userCurrentConfig, $key), $value, $fullKey);
                }
            } else {
                $this->userFacingChanges[] = [
                    'type' => self::CHANGE_ADDED,
                    'key' => $fullKey,
                    'description' => "- `{$fullKey}` will be added.",
                ];
            }

        }

    }

    protected function applyChanges()
    {
        // First, get the new config file and replace defaults with user's old values
        $ast = $this->getIncomingConfigFileAst();
        foreach ($this->catchUps as $key => $value) {
            if (preg_match('/.*\.\d+$/', $key)) {
                // Array item
                $this->pushValue($ast, preg_replace('/.\d+$/', '', $key), $value);
            } else {
                $this->setValue($ast, $key, $value);
            }
        }


        // Next, make the "migration" changes (rename config keys)
        foreach ($this->userFacingChanges as $change) {
            switch ($change['type']) {
                case self::CHANGE_REMOVED:
                    // Do nothing; the new config already doesn't have this
                    break;
                case self::CHANGE_RENAMED:
                    $this->setValue($ast, $change['new_key'], $change['new_value']);
                    break;
                case self::CHANGE_ARRAY_ITEM_ADDED:
                    $this->pushValue($ast, $change['key'], $change['value']);
                    break;
            }
        }

        // Finally, print out the changes into the user's config file (saving the old one as a backup)
        $prettyPrinter = new PrettyPrinter\Standard(['shortArraySyntax' => true]);
        $newCode = $prettyPrinter->printFormatPreserving($ast, $this->incomingConfigFileOriginalAst, $this->incomingConfigFileOriginalTokens);

        $outputFile = $this->configFiles['user_relative'];
        rename($outputFile, "$outputFile.bak");
        copy($this->configFiles['package_absolute'], $outputFile);
        file_put_contents($outputFile, $newCode);
    }

    protected function getOutgoingConfigItem(string $fullKey): ?Node\Expr
    {
        $ast = $this->getOutgoingConfigAst();
        return $this->getAstItem($ast, $fullKey);
    }

    protected function getIncomingConfigItem(string $fullKey): ?Node\Expr
    {
        $ast = $this->getIncomingConfigFileAst();
        return $this->getAstItem($ast, $fullKey);
    }

    protected function getAstItem(array $ast, string $fullKey): ?Node\Expr
    {
        $keySegments = explode('.', $fullKey);
        $nodeFinder = new NodeFinder;
        /** @var Node\Expr\ArrayItem[] $configArray */
        $configArray = $nodeFinder->findFirst(
            $ast, fn(Node $node) => $node instanceof Node\Stmt\Return_
        )->expr->items;

        $searchArray = $configArray;
        try {
            while (count($keySegments)) {
                $nextKeySegment = array_shift($keySegments);
                foreach ($searchArray as $item) {
                    if ($item->key->value === $nextKeySegment) {
                        break;
                    }
                }
                if (count($keySegments)) {
                    $searchArray = $item->value->items ?? [];
                } else {
                    return $item->value;
                }
            }
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function getOutgoingConfigAst(): ?array
    {
        if (!empty($this->outgoingConfigFileAst)) {
            return $this->outgoingConfigFileAst;
        }

        $sourceCode = file_get_contents($this->configFiles['user_relative']);

        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        return $this->outgoingConfigFileAst = $parser->parse($sourceCode);
    }

    protected function getIncomingConfigFileAst(): ?array
    {
        if (!empty($this->incomingConfigFileAstForModification)) {
            return $this->incomingConfigFileAstForModification;
        }

        $sourceCode = file_get_contents($this->configFiles['package_absolute']);

        // Doing this because we need to preserve the formatting when printing later
        $lexer = new Lexer\Emulative([
            'usedAttributes' => [
                'comments',
                'startLine', 'endLine',
                'startTokenPos', 'endTokenPos',
            ],
        ]);
        $parser = new Parser\Php7($lexer);
        $this->incomingConfigFileOriginalAst = $parser->parse($sourceCode);
        $this->incomingConfigFileOriginalTokens = $lexer->getTokens();
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NodeVisitor\CloningVisitor());
        $clonedAst = $traverser->traverse($this->incomingConfigFileOriginalAst);

        return $this->incomingConfigFileAstForModification = $clonedAst;
    }

    protected function setValue($ast, string $key, $newValue)
    {
        $keySegments = explode('.', $key);
        $nodeFinder = new NodeFinder;
        /** @var Node\Expr\ArrayItem[] $configArray */
        $configArray = $nodeFinder->findFirst(
            $ast, fn(Node $node) => $node instanceof Node\Stmt\Return_
        )->expr->items;

        $searchArray = $configArray;
        while (count($keySegments)) {
            $nextKeySegment = array_shift($keySegments);
            foreach ($searchArray as $item) {
                if ($item->key->value === $nextKeySegment) {
                    break;
                }
            }

            if (count($keySegments)) {
                $searchArray = $item->value->items ?? [];
            } else {
                $item->value = $newValue;
                return;
            }
        }
    }

    protected function pushValue($ast, string $arrayKey, $newValue)
    {
        $keySegments = explode('.', $arrayKey);
        $nodeFinder = new NodeFinder;
        /** @var Node\Expr\ArrayItem[] $configArray */
        $configArray = $nodeFinder->findFirst(
            $ast, fn(Node $node) => $node instanceof Node\Stmt\Return_
        )->expr->items;

        $searchArray = $configArray;
        while (count($keySegments)) {
            $nextKeySegment = array_shift($keySegments);
            foreach ($searchArray as $item) {
                if ($item->key->value === $nextKeySegment) {
                    break;
                }
            }
            if (count($keySegments)) {
                $searchArray = $item->value->items ?? [];
            } else {
                $item->value->items[] = $newValue;
                return;
            }
        }
    }

    /**
     * Resolve config item key with dot notation
     */
    private function getFullKey(string $key, string $rootKey = ''): string
    {
        if (empty($rootKey)) {
            return $key;
        }

        return "$rootKey.$key";
    }

    public function dontTouch(string ...$keys): self
    {
        $this->dontTouchKeys += $keys;
        return $this;
    }

    protected function canModifyKey(string $key): bool
    {
        return !in_array($key, $this->dontTouchKeys);
    }

    public function move(string $oldKey, string $newKey): self
    {
        $this->movedKeys[$oldKey] = $newKey;
        return $this;
    }

    protected function wasKeyMoved(string $oldKey): bool
    {
        return array_key_exists($oldKey, $this->movedKeys);
    }

}