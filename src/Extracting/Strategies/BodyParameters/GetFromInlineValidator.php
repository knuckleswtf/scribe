<?php

namespace Knuckles\Scribe\Extracting\Strategies\BodyParameters;

use Knuckles\Scribe\Extracting\Strategies\GetFromInlineValidatorBase;
use PhpParser\Node;

class GetFromInlineValidator extends GetFromInlineValidatorBase
{
    protected function isValidationStatementMeantForThisStrategy(Node $validationStatement): bool
    {
        // Only use this validator for body params if there's no "// Query parameters" comment above
        $comments = $validationStatement->getComments();
        $comments = implode("\n", array_map(fn ($comment) => $comment->getReformattedText(), $comments));
        if (mb_strpos(mb_strtolower($comments), 'query parameters') !== false) {
            return false;
        }

        return true;
    }
}
