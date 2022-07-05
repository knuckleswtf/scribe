<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class TestPet extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public function owners()
    {
        return $this->belongsToMany(TestUser::class)->withPivot('duration');
    }
}
