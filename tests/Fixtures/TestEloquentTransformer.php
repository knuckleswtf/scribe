<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use League\Fractal\TransformerAbstract;

class TestEloquentTransformer extends TransformerAbstract
{
    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform(Model $model)
    {
        return [
            'id' => $model->id,
            'name' => $model->name,
            'state1' => $model['state1'],
            'random-state' => $model['random-state'],
        ];
    }
}
