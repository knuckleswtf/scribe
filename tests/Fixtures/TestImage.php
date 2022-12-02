<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestImage extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return TestImageFactory::new();
    }

    public function imageable()
    {
        return $this->morphTo();
    }
}
