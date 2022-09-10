<?php

namespace Knuckles\Scribe\Tests\Fixtures;

/**
 * @group 1. Group 1
 *
 * Group 1 APIs
 */
class TestGroupController
{
    public function action1()
    {
    }

    /**
     * @group 1. Group 1
     */
    public function action1b()
    {
    }

    /**
     * @group 2. Group 2
     */
    public function action2()
    {
    }

    /** @group 10. Group 10 */
    public function action10()
    {
    }

    /**
     * @group 13. Group 13
     * @subgroup SG B
     */
    public function action13a()
    {
    }

    /**
     * @group 13. Group 13
     * @subgroup SG C
     */
    public function action13b()
    {
    }

    /**
     * @group 13. Group 13
     */
    public function action13c()
    {
    }

    /**
     * @group 13. Group 13
     * @subgroup SG B
     */
    public function action13d()
    {
    }

    /**
     * @group 13. Group 13
     * @subgroup SG A
     */
    public function action13e()
    {
    }
}
