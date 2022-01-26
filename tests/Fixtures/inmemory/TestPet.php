<?php

namespace Knuckles\Scribe\Tests\Fixtures\inmemory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestPet extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return TestPetFactory::new();
    }

    public function owners()
    {
        return $this->belongsToMany(TestUser::class)->withPivot('duration');
    }
}
