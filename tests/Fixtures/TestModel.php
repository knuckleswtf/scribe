<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A demo test model.
 *
 * @author Tobias van Beek <t.vanbeek@tjvb.nl>
 */
class TestModel extends Model
{
    public $id = 1;

    public $name = 'TestName';

    public $description = 'Welcome on this test versions';
}
