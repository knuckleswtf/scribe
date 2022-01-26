<?php

namespace Knuckles\Scribe\Tests\Fixtures\inmemory;

use \Illuminate\Database\Eloquent\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestUser extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return TestUserFactory::new();
    }

    public function children()
    {
        return $this->hasMany(TestUser::class, 'parent_id');
    }

    public function pets()
    {
        return $this->belongsToMany(TestPet::class)->withPivot('duration');
    }

    public function work()
    {
        return $this->belongsTo(TestWork::class, 'test_work_id');
    }
}
