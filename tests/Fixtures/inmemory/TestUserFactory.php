<?php

namespace Knuckles\Scribe\Tests\Fixtures\inmemory;

use Illuminate\Database\Eloquent\Factories\Factory;

class TestUserFactory extends Factory
{
    protected $model = TestUser::class;

    public function configure()
    {
        return $this->afterCreating(function (TestUser $user) {
            $user->load([
                'children.pets',
                'work.departments',
            ]);
        });
    }

    /**
     * @inheritDoc
     */
    public function definition()
    {
        return [
            'first_name' => 'Tested',
            'last_name' => 'Again',
            'email' => 'a@b.com',
            'test_work_id' => TestWork::factory(),
        ];
    }
}
