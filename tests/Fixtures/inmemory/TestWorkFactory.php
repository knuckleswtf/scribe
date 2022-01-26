<?php

namespace Knuckles\Scribe\Tests\Fixtures\inmemory;

use Illuminate\Database\Eloquent\Factories\Factory;

class TestWorkFactory extends Factory
{
    protected $model = TestWork::class;

    /**
     * @inheritDoc
     */
    public function definition()
    {
        return [
            'name' => 'My best work',
        ];
    }
}
