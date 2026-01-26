<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class TestPostFactory extends Factory
{
    protected $model = TestPost::class;

    public function definition(): array
    {
        return [
            'id' => 1,
            'title' => 'Test title',
            'body' => 'random body',
        ];
    }

    public function pivotTags(): array
    {
        return [
            'priority' => 'high',
        ];
    }
}
