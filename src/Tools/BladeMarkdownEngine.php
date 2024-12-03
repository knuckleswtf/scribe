<?php

namespace Knuckles\Scribe\Tools;


use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\CompilerInterface;
use Illuminate\View\Engines\CompilerEngine;
use Parsedown;

class BladeMarkdownEngine extends CompilerEngine
{
    private Parsedown $markdown;

    public function __construct(CompilerInterface $compiler, ?Filesystem $files = null)
    {
        parent::__construct($compiler, $files ?: new Filesystem);
        $this->markdown = Parsedown::instance();
    }

    /**
     * Get the evaluated contents of the view.
     *
     */
    public function get($path, array $data = [])
    {
        $contents = parent::get($path, $data);

        return $this->markdown->text($contents);
    }
}
