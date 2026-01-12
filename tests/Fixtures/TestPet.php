<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class TestPet extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    public function owners()
    {
        return $this->belongsToMany(TestUser::class)->withPivot('duration');
    }
}
