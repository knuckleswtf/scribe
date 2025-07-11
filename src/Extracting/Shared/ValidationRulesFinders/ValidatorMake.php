<?php

namespace Knuckles\Scribe\Extracting\Shared\ValidationRulesFinders;

use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * This class looks for
 *   $validator = Validator::make($request, ...)
 *   Validator::make($request, ...)->validate()
 *
 * The variable names (`$validator` and `$request`) don't matter.
 */
class ValidatorMake
{
    public static function find(Node $node)
    {
        // Make sure it's an assignment
        if (! ($node instanceof Node\Stmt\Expression)) {
            return;
        }

        $validatorNode = (new NodeFinder)->findFirst($node, function ($node): bool {
            return $node instanceof Node\Expr\StaticCall
                && ! empty($node->class->name)
                && str_ends_with($node->class->name, 'Validator')
                && $node->name->name == 'make';
        });

        if ($validatorNode instanceof Node\Expr\StaticCall) {
            return $validatorNode->args[1]->value;
        }
    }
}
