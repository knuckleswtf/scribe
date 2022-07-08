<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class TestPost extends Model
{
    public function getRouteKeyName()
    {
        return 'slug';
    }
}
