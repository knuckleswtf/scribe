<?php

namespace Knuckles\Scribe\Tests\Unit;

use Knuckles\Scribe\Tests\BaseLaravelTest;
use Illuminate\Foundation\Testing\Concerns\InteractsWithViews;

class FieldRenderTest extends BaseLaravelTest
{
    use InteractsWithViews;

    /** @test */
    public function can_render_a_field_with_an_example_value()
    {
        $data = [
            'name' => 'Parameter Name',
            'description' => 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod.',
            'example' => 'My Custom Value',
            'endpointId' => 'example-requests-GET',
        ];

        $view = $this->view(
            'scribe::components.field-details', array_merge($data, [
                'type' => 'string',
                'required' => true,
                'component' => 'url',
            ])
        );

        foreach ($data as $text) {
            $view->assertSee($text);
        }
    }
}
