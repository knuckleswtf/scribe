<?php

namespace Knuckles\Scribe\Tests\Fixtures;

/**
 * Only routed from tests that skipped themselves if JSON:API resources are unavailable.
 * The resource class names here are plain docblock strings, so nothing in this file
 * autoloads a JSON:API class on versions that don't have them.
 */
class TestJsonApiController
{
    /**
     * A JSON:API post.
     *
     * @apiResource \Knuckles\Scribe\Tests\Fixtures\TestPostJsonApiResource
     *
     * @apiResourceModel \Knuckles\Scribe\Tests\Fixtures\TestPost with=tags
     */
    public function showPost() {}

    /**
     * @apiResource \Knuckles\Scribe\Tests\Fixtures\TestEmptyJsonApiResource
     */
    public function resourceWithoutAModel() {}
}
