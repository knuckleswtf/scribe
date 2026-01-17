<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class TestUser extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function pets()
    {
        return $this->belongsToMany(TestPet::class)->withPivot('duration');
    }
}
