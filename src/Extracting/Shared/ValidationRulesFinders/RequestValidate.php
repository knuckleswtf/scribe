<?php

namespace Knuckles\Scribe\Extracting\Shared\ValidationRulesFinders;

use PhpParser\Node;

/**
 * This class looks for
 *   $anyVariable = $request->validate(...);
 * or just
 *   $request->validate(...);.
 *
 * Also supports `$req` instead of `$request`
 * Also supports `->validateWithBag('', ...)`
 */
class RequestValidate
{
    public static function find(Node $node)
    {
        if (!$node instanceof Node\Stmt\Expression) {
            return;
        }

        $expr = $node->expr;
        if ($expr instanceof Node\Expr\Assign) {
            $expr = $expr->expr; // If it's an assignment, get the expression on the RHS
        }

        if (
            $expr instanceof Node\Expr\MethodCall
            && $expr->var instanceof Node\Expr\Variable
            && in_array($expr->var->name, ['request', 'req'])
        ) {
            if ('validate' == $expr->name->name) {
                return $expr->args[0]->value;
            }

            if ('validateWithBag' == $expr->name->name) {
                return $expr->args[1]->value;
            }
        }
    }
}
