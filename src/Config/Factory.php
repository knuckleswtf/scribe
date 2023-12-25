<?php

namespace Knuckles\Scribe\Config;

class Factory
{
    public static function make(Extracting $extracting, Output $output): array
    {
        return Serializer::toOldConfig($extracting, $output);
    }
}
