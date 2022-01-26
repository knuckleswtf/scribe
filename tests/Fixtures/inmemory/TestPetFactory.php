<?php

namespace Knuckles\Scribe\Tests\Fixtures\inmemory;

use Illuminate\Database\Eloquent\Factories\Factory;

class TestPetFactory extends Factory
{
    protected $model = TestPet::class;

    public function definition()
    {
        return [
            'name' => 'Mephistopheles',
            'species' => 'dog',
        ];
    }
}
