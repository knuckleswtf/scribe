<?php

namespace Knuckles\Scribe\Extracting\Strategies\QueryParameters;

use Knuckles\Scribe\Extracting\Strategies\GetFromInlineValidatorBase;
use PhpParser\Node;

class GetFromInlineValidator extends GetFromInlineValidatorBase
{
    protected function isAssignmentMeantForThisStrategy(Node\Expr\Assign $validationAssignmentExpression): bool
    {
        // Only use this validator for query params if there's a "// Query parameters" comment above
        $comments = $validationAssignmentExpression->getComments();
        $comments = join("\n", array_map(fn($comment) => $comment->getReformattedText(), $comments));
        if (strpos(strtolower($comments), "query parameters") !== false) {
            return true;
        }

        return false;
    }
}

