<?php

namespace Knuckles\Scribe\Tests\Unit;

use Illuminate\Routing\Route;
use Knuckles\Scribe\Tests\BaseUnitTest;
use Knuckles\Scribe\Tools\RoutePatternMatcher;

class RoutePatternMatcherTest extends BaseUnitTest
{
    /** @test */
    public function matches_by_route_name()
    {
        $route = new Route(["POST"], "/abc", ['as' => 'users.show']);
        $this->assertTrue(RoutePatternMatcher::matches($route, ['users.show']));
        $this->assertTrue(RoutePatternMatcher::matches($route, ['users.*']));
        $this->assertFalse(RoutePatternMatcher::matches($route, ['users.index']));
    }

    /** @test */
    public function matches_by_route_method_and_path()
    {
        $route = new Route(["POST"], "/abc", ['as' => 'users.show']);
        $this->assertTrue(RoutePatternMatcher::matches($route, ["POST /abc"]));
        $this->assertTrue(RoutePatternMatcher::matches($route, ["POST abc"]));
        $this->assertTrue(RoutePatternMatcher::matches($route, ["POST ab*"]));
        $this->assertTrue(RoutePatternMatcher::matches($route, ["POST /ab*"]));
        $this->assertTrue(RoutePatternMatcher::matches($route, ["POST *"]));
        $this->assertTrue(RoutePatternMatcher::matches($route, ["* abc"]));
        $this->assertTrue(RoutePatternMatcher::matches($route, ["* /abc"]));
        $this->assertTrue(RoutePatternMatcher::matches($route, ["* *"]));

        $this->assertFalse(RoutePatternMatcher::matches($route, ["GET /abc"]));
        $this->assertFalse(RoutePatternMatcher::matches($route, ["GET abc"]));
    }

    /** @test */
    public function matches_by_route_path()
    {
        $route = new Route(["POST"], "/abc", ['as' => 'users.show']);
        $this->assertTrue(RoutePatternMatcher::matches($route, ["/abc"]));
        $this->assertTrue(RoutePatternMatcher::matches($route, ["abc"]));
        $this->assertTrue(RoutePatternMatcher::matches($route, ["ab*"]));
        $this->assertTrue(RoutePatternMatcher::matches($route, ["/ab*"]));
        $this->assertTrue(RoutePatternMatcher::matches($route, ["*"]));

        $this->assertFalse(RoutePatternMatcher::matches($route, ["/d*"]));
        $this->assertFalse(RoutePatternMatcher::matches($route, ["d*"]));
    }

    /** @test */
    public function matches_route_with_multiple_methods()
    {
        $route = new Route(["GET", "HEAD"], "/abc", ['as' => 'users.show']);
        $this->assertTrue(RoutePatternMatcher::matches($route, ["HEAD /abc"]));
        $this->assertTrue(RoutePatternMatcher::matches($route, ["GET abc"]));
        $this->assertFalse(RoutePatternMatcher::matches($route, ["POST abc"]));
    }

}
