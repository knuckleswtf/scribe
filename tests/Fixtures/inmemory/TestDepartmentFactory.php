<?php

namespace Knuckles\Scribe\Tests\Fixtures\inmemory;

use Illuminate\Database\Eloquent\Factories\Factory;

class TestDepartmentFactory extends Factory
{
    protected $model = TestDepartment::class;

    /**
     * @inheritDoc
     */
    public function definition()
    {
        return [
            'name' => 'My best department',
            'test_work_id' => TestWork::factory(),
        ];
    }
}
