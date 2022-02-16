<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class TestPet extends Model
{

    public function owners()
    {
        return $this->belongsToMany(TestUser::class)->withPivot('duration');
    }
}
