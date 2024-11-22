<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use League\Fractal\Resource\ResourceInterface;
use League\Fractal\TransformerAbstract;


class TestUserTransformer extends TransformerAbstract
{
    protected array $defaultIncludes = ['children'];

    public function transform(TestUser $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model->first_name . ' ' . $model->last_name,
            'email' => $model->email,
            'children_count' => $model->getAttribute('children_count'),
        ];
    }

    public function includeChildren(TestUser $model): ResourceInterface
    {
        return $model->children
            ? $this->collection($model->children, new TestUserTransformer())
            : $this->null();
    }
}
