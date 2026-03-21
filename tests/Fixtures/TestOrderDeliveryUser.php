<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestOrderDeliveryUser extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected static function newFactory()
    {
        return TestOrderDeliveryUserFactory::new();
    }
}
