<?php


namespace Knuckles\Camel\Tools;

use Illuminate\Support\Str;
use Knuckles\Camel\Output\Group;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Symfony\Component\Yaml\Yaml;


class Loader
{
    /**
     * @param string $folder
     * @return Group[]
     */
    public static function loadEndpoints(string $folder): array
    {
        $adapter = new Local(getcwd());
        $fs = new Filesystem($adapter);
        $contents = $fs->listContents($folder);;

        $groups = [];
        foreach ($contents as $object) {
            if ($object['type'] == 'file' && Str::endsWith($object['basename'], '.yaml')) {
                $groups[] = Group::createFromSpec(Yaml::parseFile($object['path']));
            }
        }
        return $groups;
    }
}