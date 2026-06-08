<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class TestOrderFactory extends Factory
{
    protected $model = TestOrder::class;

    public function definition(): array
    {
        return [
            'status_id' => null,
            'delivery_id' => null,
        ];
    }
}
