<?php

namespace Knuckles\Scribe\Tests\Unit;

use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tools\Utils;

class UtilsTest extends BaseLaravelTest
{
    /** @test */
    public function make_directory_recursive()
    {
        $dir = __DIR__ . '/test_dir';
        Utils::makeDirectoryRecursive($dir);
        $this->assertDirectoryExists($dir); // Directory exists

        if (rmdir($dir)) { // Remove the directory
            dump("Directory deleted successfully: $dir");
        } else { // If deletion fails, you can handle the error as needed
            dump("Failed to delete directory: $dir");
        }
    }
}
