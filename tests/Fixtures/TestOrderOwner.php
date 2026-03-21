<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestOrderOwner extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    public function order(): BelongsTo
    {
        return $this->belongsTo(TestOrder::class, 'order_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TestOrderStatus::class, 'status_id');
    }

    protected static function newFactory()
    {
        return TestOrderOwnerFactory::new();
    }
}
