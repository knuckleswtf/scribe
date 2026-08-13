<?php

namespace Knuckles\Scribe\Tests;

use PHPUnit\Framework\Assert;

/**
 * Drop-in replacement for the `dms/phpunit-arraysubset-asserts` package
 * (`DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts`), which was removed
 * from require-dev because it caps phpunit at ^9 || ^10 and thereby caps the
 * whole dev tree at Laravel 11.
 *
 * The semantics of the original are reproduced exactly: the subset is patched
 * into the array with array_replace_recursive(), and the result is compared to
 * the original array.
 *
 * This file is excluded from Pint (see `notPath` in pint.json): the
 * `strict_comparison` rule would rewrite the loose comparison below into a
 * strict one and silently change what these assertions accept.
 */
trait ArraySubsetAsserts
{
    /**
     * Asserts that an array has a specified subset.
     */
    public static function assertArraySubset(array $subset, array $array, bool $checkForIdentity = false, string $message = ''): void
    {
        $patched = array_replace_recursive($array, $subset);

        // Loose comparison, matching the original package: it compares with ==,
        // where e.g. [] == null holds. assertEquals() below is stricter than
        // that (it fails on the type mismatch), so it is only reached once the
        // arrays are known to differ — it is there to render the diff.
        $matches = $checkForIdentity ? $patched === $array : $patched == $array;

        if ($matches) {
            Assert::assertTrue(true);

            return;
        }

        $checkForIdentity
            ? Assert::assertSame($patched, $array, $message)
            : Assert::assertEquals($patched, $array, $message);
    }
}
