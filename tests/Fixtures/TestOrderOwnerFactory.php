<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class TestOrderOwnerFactory extends Factory
{
    protected $model = TestOrderOwner::class;

    public function definition(): array
    {
        return [
            'order_id' => null,
            'status_id' => null,
        ];
    }
}
