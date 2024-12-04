<?php

namespace Knuckles\Scribe\Extracting\Shared\ValidationRulesFinders;

use PhpParser\Node;

/**
 * This class looks for
 *   $anyVariable = Request::validate(...);
 * or just
 *   Request::validate(...);
 *
 * Also supports `->validateWithBag('', ...)`
 */
class RequestValidateFacade
{
    public static function find(Node $node)
    {
        if (!($node instanceof Node\Stmt\Expression)) return;

        $expr = $node->expr;
        if ($expr instanceof Node\Expr\Assign) {
            $expr = $expr->expr; // If it's an assignment, get the expression on the RHS
        }

        if (
            $expr instanceof Node\Expr\StaticCall
            && $expr->class instanceof Node\Name
            && in_array($expr->class->name, ['Request', \Illuminate\Support\Facades\Request::class])
        ) {
            if ($expr->name->name === "validate") {
                return $expr->args[0]->value;
            }

            if ($expr->name->name === "validateWithBag") {
                return $expr->args[1]->value;
            }
        }
    }
}
