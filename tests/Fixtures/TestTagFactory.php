<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class TestTagFactory extends Factory
{
    protected $model = TestTag::class;

    public function definition(): array
    {
        return [
            'id' => 1,
            'name' => 'tag 1',
        ];
    }
}
