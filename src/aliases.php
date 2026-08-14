<?php

/*
 * Backwards-compatibility aliases for the DocBlock classes.
 *
 * Scribe used to parse docblocks with mpociot/reflection-docblock, and its strategy API exposes
 * that package's classes — UseTransformerTags::getTransformerResponseFromTag() takes a Tag,
 * RouteDocBlocker::getDocBlocksFromRoute() returns DocBlocks, and so on. Custom strategies out
 * there therefore type-hint Mpociot\Reflection\DocBlock and Mpociot\Reflection\DocBlock\Tag.
 *
 * That package has been unmaintained since 2016
 *
 * They are deprecated and will be removed in the next major version: type-hint
 * Barryvdh\Reflection\DocBlock and Barryvdh\Reflection\DocBlock\Tag instead.
 *
 * This is registered as an autoloader rather than aliasing eagerly so that we never shadow the
 * real classes: if mpociot/reflection-docblock is still installed as some other package's
 * dependency, Composer's autoloader resolves those names first and this one is never reached.
 */

spl_autoload_register(function (string $class): void {
    static $aliases = [
        'Mpociot\Reflection\DocBlock' => Barryvdh\Reflection\DocBlock::class,
        'Mpociot\Reflection\DocBlock\Context' => Barryvdh\Reflection\DocBlock\Context::class,
        'Mpociot\Reflection\DocBlock\Description' => Barryvdh\Reflection\DocBlock\Description::class,
        'Mpociot\Reflection\DocBlock\Location' => Barryvdh\Reflection\DocBlock\Location::class,
        'Mpociot\Reflection\DocBlock\Tag' => Barryvdh\Reflection\DocBlock\Tag::class,
    ];

    if (isset($aliases[$class])) {
        class_alias($aliases[$class], $class);
    }
});
