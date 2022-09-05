<?php

namespace Knuckles\Scribe\Matching;

use Illuminate\Routing\Route;

class MatchedRoute implements \ArrayAccess
{
    protected Route $route;

    protected array $rules;

    public function __construct(Route $route, array $applyRules)
    {
        $this->route = $route;
        $this->rules = $applyRules;
    }

    public function getRoute(): Route
    {
        return $this->route;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function offsetExists($offset): bool
    {
        return is_callable([$this, 'get' . ucfirst($offset)]);
    }

    public function offsetGet($offset): mixed
    {
        return call_user_func([$this, 'get' . ucfirst($offset)]);
    }

    public function offsetSet($offset, $value): void
    {
        $this->$offset = $value;
    }

    public function offsetUnset($offset): void
    {
        $this->$offset = null;
    }
}
