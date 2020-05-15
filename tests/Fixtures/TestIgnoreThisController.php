<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Routing\Controller;

/**
 * @hideFromAPIDocumentation
 * @group Group A
 */
class TestIgnoreThisController extends Controller
{
    public function dummy()
    {
        return '';
    }
}
