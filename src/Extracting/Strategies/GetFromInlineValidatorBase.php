<?php

namespace Knuckles\Scribe\Extracting\Strategies;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\MethodAstParser;
use Knuckles\Scribe\Extracting\ParsesValidationRules;
use Knuckles\Scribe\Extracting\ValidationRulesFinders\RequestValidate;
use Knuckles\Scribe\Extracting\ValidationRulesFinders\ThisValidate;
use Knuckles\Scribe\Extracting\ValidationRulesFinders\ValidatorMake;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;

class GetFromInlineValidatorBase extends Strategy
{
    use ParsesValidationRules;

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules): ?array
    {
        if (!$endpointData->method instanceof \ReflectionMethod) {
            return [];
        }

        $methodAst = MethodAstParser::getMethodAst($endpointData->method);
        [$validationRules, $customParameterData] = $this->lookForInlineValidationRules($methodAst);

        $bodyParametersFromValidationRules = $this->getParametersFromValidationRules($validationRules, $customParameterData);
        return $this->normaliseArrayAndObjectParameters($bodyParametersFromValidationRules);
    }

    public function lookForInlineValidationRules(ClassMethod $methodAst): array
    {
        // Validation usually happens early on, so let's assume it's in the first 10 statements
        $statements = array_slice($methodAst->stmts, 0, 10);

        // Todo remove in future
        if (method_exists($this, 'isAssignmentMeantForThisStrategy')) {
            c::error("A custom strategy of yours is using a removed method isAssignmentMeantForThisStrategy().\n");
            c::error("Fix this by changing the method name to isValidationStatementMeantForThisStrategy()\n");
            c::error("and changing the type of its argument to Node.\n");
            exit(1);
        }

        [$index, $validationStatement, $validationRules] = $this->findValidationExpression($statements);

        if ($validationStatement &&
            !$this->isValidationStatementMeantForThisStrategy($validationStatement)) {
            return [[], []];
        }

        // If validation rules were saved in a variable (like $rules),
        // try to find the var and expand the value
        if ($validationRules instanceof Node\Expr\Variable) {
            foreach (array_reverse(array_slice($statements, 0, $index)) as $earlierStatement) {
                if (
                    $earlierStatement instanceof Node\Stmt\Expression
                    && $earlierStatement->expr instanceof Node\Expr\Assign
                    && $earlierStatement->expr->var instanceof Node\Expr\Variable
                    && $earlierStatement->expr->var->name == $validationRules->name
                ) {
                    $validationRules = $earlierStatement->expr->expr;
                    break;
                }
            }
        }

        if (!$validationRules instanceof Node\Expr\Array_) {
            return [[], []];
        }

        $rules = [];
        $customParameterData = [];
        foreach ($validationRules->items as $item) {
            /** @var Node\Expr\ArrayItem $item */
            if (!$item->key instanceof Node\Scalar\String_) {
                continue;
            }

            $paramName = $item->key->value;

            // Might be an expression or concatenated string, etc.
            // For now, let's focus on simple strings and arrays of strings
            if ($item->value instanceof Node\Scalar\String_) {
                $rules[$paramName] = $item->value->value;
            } else if ($item->value instanceof Node\Expr\Array_) {
                $rulesList = [];
                foreach ($item->value->items as $arrayItem) {
                    /** @var Node\Expr\ArrayItem $arrayItem */
                    if ($arrayItem->value instanceof Node\Scalar\String_) {
                        $rulesList[] = $arrayItem->value->value;
                    }

                }
                $rules[$paramName] = join('|', $rulesList);
            } else {
                $rules[$paramName] = [];
                continue;
            }

            $description = $example = null;
            $comments = join("\n", array_map(
                    fn($comment) => ltrim(ltrim($comment->getReformattedText(), "/")),
                    $item->getComments()
                )
            );

            if ($comments) {
                $description = trim(str_replace(['No-example.', 'No-example'], '', $comments));
                $example = null;
                if (preg_match('/(.*\s+|^)Example:\s*([\s\S]+)\s*/s', $description, $matches)) {
                    $description = trim($matches[1]);
                    $example = $matches[2];
                }
            }

            $customParameterData[$paramName] = compact('description', 'example');
        }

        return [$rules, $customParameterData];
    }

    protected function getMissingCustomDataMessage($parameterName)
    {
        return "No extra data found for parameter '$parameterName' from your inline validator. You can add a comment above '$parameterName' with a description and example.";
    }

    protected function shouldCastUserExample()
    {
        return true;
    }

    protected function isValidationStatementMeantForThisStrategy(Node $validationStatement): bool
    {
        return true;
    }

    protected function findValidationExpression($statements): ?array
    {
        $strategies = [
            RequestValidate::class, // $request->validate(...);
            ValidatorMake::class, // Validator::make($request, ...)
            ThisValidate::class, // $this->validate(...);
        ];

        foreach ($statements as $index => $node) {
            foreach ($strategies as $strategy) {
                if ($validationRules = $strategy::find($node)) {
                    return [$index, $node, $validationRules];
                }
            }
        }

        return [null, null, null];
    }
}
