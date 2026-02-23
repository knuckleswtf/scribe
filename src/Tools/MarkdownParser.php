<?php

namespace Knuckles\Scribe\Tools;

use Illuminate\Support\Str;

class MarkdownParser extends \Parsedown
{
    public array $headings = [];

    protected function blockHeader($Line)
    {
        $block = parent::blockHeader($Line);
        if (isset($block['element']['name'])) {
            $text = $block['element']['text']
                ?? $block['element']['handler']['argument']
                ?? '';
            $text = is_string($text) ? $text : '';
            $level = (int) mb_trim($block['element']['name'], 'h');
            $slug = Str::slug($text);
            $block['element']['attributes']['id'] = $slug;
            $this->headings[] = [
                'text' => $text,
                'level' => $level,
                'slug' => $slug,
            ];
        }

        return $block;
    }
}
