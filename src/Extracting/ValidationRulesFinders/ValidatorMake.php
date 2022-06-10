<?php

namespace Knuckles\Scribe\Extracting\ValidationRulesFinders;

use PhpParser\Node;

/**
 * This class looks for
 *   $validator = Validator::make($request, ...)
 *
 * The variable names (`$validator` and `$request`) don't matter.
 */
class ValidatorMake
{
    public static function find(Node $node)
    {
        // Make sure it's an assignment
        if (!($node instanceof Node\Stmt\Expression)
            || !($node->expr instanceof Node\Expr\Assign)) {
            return;
        }

        $expr = $node->expr->expr; // Get the expression on the RHS

        if (
            $expr instanceof Node\Expr\StaticCall
            && !empty($expr->class->parts)
            && end($expr->class->parts) == "Validator"
            && $expr->name->name == "make"
        ) {
            return $expr->args[1]->value;
        }
    }
}