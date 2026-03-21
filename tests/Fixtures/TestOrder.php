<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestOrder extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    public function status(): BelongsTo
    {
        return $this->belongsTo(TestOrderStatus::class, 'status_id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(TestOrderDelivery::class, 'delivery_id');
    }

    protected static function newFactory()
    {
        return TestOrderFactory::new();
    }
}
