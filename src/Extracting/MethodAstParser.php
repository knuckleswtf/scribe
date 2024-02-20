<?php

namespace Knuckles\Scribe\Extracting;

use Exception;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use ReflectionFunctionAbstract;
use Throwable;

/**
 * MethodAstParser
 * Utility class to help with retrieving (and caching) ASTs of route methods.
 */
class MethodAstParser
{
    protected static array $methodAsts = [];
    protected static array $classAsts = [];

    public static function getMethodAst(ReflectionFunctionAbstract $method)
    {
        $methodName = $method->name;
        $fileName = $method->getFileName();

        $methodAst = self::getCachedMethodAst($fileName, $methodName);

        if ($methodAst) {
            return $methodAst;
        }

        $classAst = self::getClassAst($fileName);
        $methodAst = self::findMethodInClassAst($classAst, $methodName);
        self::cacheMethodAst($fileName, $methodName, $methodAst);

        return $methodAst;
    }

    /**
     * @param string $sourceCode
     *
     * @return \PhpParser\Node\Stmt[]|null
     */
    protected static function parseClassSourceCode(string $sourceCode): ?array
    {
        $parser = (new ParserFactory)->createForHostVersion();
        try {
            $ast = $parser->parse($sourceCode);
        } catch (Throwable $error) {
            throw new Exception("Parse error: {$error->getMessage()}");
        }

        return $ast;
    }

    /**
     * @param \PhpParser\Node\Stmt[] $ast
     * @param string $methodName
     *
     * @return Node|null
     */
    protected static function findMethodInClassAst(array $ast, string $methodName)
    {
        $nodeFinder = new NodeFinder;

        return $nodeFinder->findFirst($ast, function(Node $node) use ($methodName) {
            // Todo handle closures
            return $node instanceof Node\Stmt\ClassMethod
                && $node->name->toString() === $methodName;
        });
    }

    protected static function getCachedMethodAst(string $fileName, string $methodName)
    {
        $key = self::getAstCacheId($fileName, $methodName);
        return self::$methodAsts[$key] ?? null;
    }

    protected static function cacheMethodAst(string $fileName, string $methodName, Node $methodAst)
    {
        $key = self::getAstCacheId($fileName, $methodName);
        self::$methodAsts[$key] = $methodAst;
    }

    private static function getAstCacheId(string $fileName, string $methodName): string
    {
        return $fileName . "///". $methodName;
    }

    private static function getClassAst(string $fileName)
    {
        $classAst = self::$classAsts[$fileName]
            ?? self::parseClassSourceCode(file_get_contents($fileName));
        return self::$classAsts[$fileName] = $classAst;
    }
}
