<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class TestOrderDeliveryFactory extends Factory
{
    protected $model = TestOrderDelivery::class;

    public function definition(): array
    {
        return [
            'name' => 'express',
            'status_id' => null,
            'user_id' => null,
        ];
    }
}
