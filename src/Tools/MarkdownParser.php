<?php

namespace Knuckles\Scribe\Tools;

use Illuminate\Support\Str;
use Parsedown;

class MarkdownParser extends Parsedown
{
    public array $headings = [];

    protected function blockHeader($Line)
    {
        $block = parent::blockHeader($Line);
        if (isset($block['element']['name'])) {
            $level = (int) trim($block['element']['name'], 'h');
            $slug = Str::slug($block['element']['text']);
            $block['element']['attributes']['id'] = $slug;
            $this->headings[] = [
                'text' => $block['element']['text'],
                'level' => $level,
                'slug' => $slug,
            ];
        }

        return $block;
    }
}