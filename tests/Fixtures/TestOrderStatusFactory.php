<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class TestOrderStatusFactory extends Factory
{
    protected $model = TestOrderStatus::class;

    public function definition(): array
    {
        return [
            'name' => 'pending',
        ];
    }
}
