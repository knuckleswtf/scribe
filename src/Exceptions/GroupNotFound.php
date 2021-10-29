<?php

namespace Knuckles\Scribe\Exceptions;

class GroupNotFound extends \RuntimeException implements ScribeException
{
    public static function forTag(string $groupName, string $tag)
    {
        return new self(
            <<<MESSAGE
You specified the group "$groupName" in a "$tag" field in one of your custom endpoints, but we couldn't find that group.
Did you rename the group?
MESSAGE

        );
    }
}
