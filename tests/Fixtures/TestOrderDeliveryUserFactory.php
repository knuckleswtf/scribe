<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class TestOrderDeliveryUserFactory extends Factory
{
    protected $model = TestOrderDeliveryUser::class;

    public function definition(): array
    {
        return [
            'name' => 'john',
        ];
    }
}
