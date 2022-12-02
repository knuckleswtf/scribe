<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class TestTag extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return TestTagFactory::new();
    }

    public function posts(): MorphToMany
    {
        return $this->morphedByMany(TestPost::class, 'taggable')->withPivot('priority');
    }
}
