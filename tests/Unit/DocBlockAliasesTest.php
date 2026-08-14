<?php

namespace Knuckles\Scribe\Tests\Unit;

use Barryvdh\Reflection\DocBlock;
use Barryvdh\Reflection\DocBlock\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Scribe's strategy API used to expose mpociot/reflection-docblock's classes, so custom strategies
 * type-hint them. src/aliases.php keeps those names working now that we're on
 * barryvdh/reflection-docblock. Deprecated — to be removed in the next major version.
 *
 * @internal
 *
 * @coversNothing
 */
class DocBlockAliasesTest extends TestCase
{
    /**
     * @dataProvider legacyClassNames
     */
    #[DataProvider('legacyClassNames')]
    public function test_legacy_docblock_class_names_still_resolve(string $legacy, string $current)
    {
        $this->assertTrue(class_exists($legacy));
        $this->assertSame($current, (new \ReflectionClass($legacy))->getName());
    }

    public static function legacyClassNames(): array
    {
        return [
            ['Mpociot\Reflection\DocBlock', DocBlock::class],
            ['Mpociot\Reflection\DocBlock\Context', DocBlock\Context::class],
            ['Mpociot\Reflection\DocBlock\Description', DocBlock\Description::class],
            ['Mpociot\Reflection\DocBlock\Location', DocBlock\Location::class],
            ['Mpociot\Reflection\DocBlock\Tag', Tag::class],
        ];
    }

    public function test_objects_from_scribe_satisfy_legacy_type_hints()
    {
        $docBlock = new DocBlock("/**\n * Do a thing.\n *\n * @bodyParam name string required\n */");

        $this->assertInstanceOf('Mpociot\Reflection\DocBlock', $docBlock);
        $this->assertInstanceOf('Mpociot\Reflection\DocBlock\Tag', $docBlock->getTags()[0]);
    }
}
