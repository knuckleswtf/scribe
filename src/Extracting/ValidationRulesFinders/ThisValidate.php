<?php

namespace Knuckles\Scribe\Extracting\ValidationRulesFinders;

use PhpParser\Node;

/**
 * This class looks for
 *   $anyVariable = $this->validate($request, ...);
 * or just
 *   $this->validate($request, ...);
 *
 * Also supports `$req` instead of `$request`
 */
class ThisValidate
{
    public static function find(Node $node)
    {
        if (!($node instanceof Node\Stmt\Expression)) return;

        $expr = $node->expr;
        if ($expr instanceof Node\Expr\Assign) {
            $expr = $expr->expr; // If it's an assignment, get the expression on the RHS
        }

        if (
            $expr instanceof Node\Expr\MethodCall
            && $expr->var instanceof Node\Expr\Variable
            && $expr->var->name === "this"
        ) {
            if ($expr->name->name == "validate") {
                return $expr->args[1]->value;
            }
        }
    }
}