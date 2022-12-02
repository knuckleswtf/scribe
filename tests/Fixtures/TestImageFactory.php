<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class TestImageFactory extends Factory
{
    protected $model = TestImage::class;

    public function definition(): array
    {
        return [
            'id' => 1,
            'url' => 'https://test.com',
        ];
    }
}
