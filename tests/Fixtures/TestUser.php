<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class TestUser extends Model
{

    public function children()
    {
        return $this->hasMany(TestUser::class, 'parent_id');
    }
}
