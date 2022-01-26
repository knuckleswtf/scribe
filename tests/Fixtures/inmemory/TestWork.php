<?php

namespace Knuckles\Scribe\Tests\Fixtures\inmemory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestWork extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return TestWorkFactory::new();
    }

    public function users()
    {
        return $this->hasMany(TestUser::class);
    }

    public function departments()
    {
        return $this->hasMany(TestDepartment::class, 'test_work_id');
    }
}
