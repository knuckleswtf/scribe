<?php

namespace Knuckles\Scribe\Tests\Fixtures\inmemory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestDepartment extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return TestDepartmentFactory::new();
    }

    public function work()
    {
        return $this->belongsTo(TestWork::class);
    }
}
