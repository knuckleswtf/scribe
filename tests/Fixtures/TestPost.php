<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class TestPost extends Model
{
    use HasFactory;

    public function getRouteKeyName()
    {
        return 'slug';
    }

    protected static function newFactory()
    {
        return TestPostFactory::new();
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(TestTag::class, 'taggable')->withPivot('priority');
    }
}
