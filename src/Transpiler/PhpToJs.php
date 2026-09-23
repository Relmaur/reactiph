<?php

declare(strict_types=1);

namespace Reactiph\Transpiler;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;

/**
 * Transpiles a single PHP method's source into an equivalent JS function,
 * for the same method to run identically on the server (as real PHP) and
 * on the client (as this transpiled JS) — see ADR 0001.
 *
 * Supports an explicit, documented subset of PHP (ADR 0003): reading or
 * writing a property (`$this->prop`) or local variable, method calls with
 * positional arguments (`$this->method(...)`), arithmetic (`+ - * / %`),
 * increment/decrement (`++ --`), strict comparison (`=== !==`), relational
 * comparison (`< <= > >=`), boolean operators (`&& || !`), string
 * concatenation (`.`), `if`/`elseif`/`else`, `while`, `for`, `foreach`,
 * array literals/access, and `return`. Anything else — loose comparison,
 * named/variadic arguments, array append syntax (`$arr[] = ...`), mixed
 * or gapped array keys — throws {@see TranspileException} rather than
 * producing wrong JS. See ADR 0011/0012/0013 for why this particular
 * subset, and for the PHP/JS semantic gaps (truthiness, loose equality,
 * array duality) it works around rather than ignores; see
 * `docs/STATUS.md` for what's still deferred (currently: stdlib builtins).
 *
 * PHP variables are function-scoped, not block-scoped, so every local
 * variable the method assigns anywhere — including a `for`/`foreach`
 * loop's own counter/key/value — is hoisted to a single `let` declaration
 * at the top of the generated JS function — this matters for a variable
 * assigned inside an `if` branch (or a loop) and read after it, which
 * would otherwise be out of scope in JS (`let` is block-scoped there).
 * For the same reason, a transpiled `foreach` is generated as a plain
 * inline `for...of` loop, never a callback — see
 * `packages/runtime-js/php-runtime.js`'s `__phpEntries()` doc comment for
 * why.
 */
final class PhpToJs
{
    /** @var array<string, true> */
    private array $declaredLocals = [];

    public function transpileMethod(string $phpMethodSource): string
    {
        $this->declaredLocals = [];

        $method = $this->parseMethod($phpMethodSource);
        $stmts = $method->stmts ?? [];

        $this->collectLocalVariables($stmts);

        $body = $this->compileStatements($stmts);
        $declarations = $this->declaredLocals === []
            ? ''
            : 'let ' . implode(', ', array_keys($this->declaredLocals)) . ";\n";

        return sprintf("function %s() {\n%s%s}\n", $method->name->toString(), $declarations, $body);
    }

    private function parseMethod(string $phpMethodSource): Stmt\ClassMethod
    {
        $wrapped = '<?php class __ReactiphTranspileTarget { ' . $phpMethodSource . ' }';

        try {
            $ast = (new ParserFactory())->createForHostVersion()->parse($wrapped);
        } catch (\PhpParser\Error $e) {
            throw TranspileException::phpSyntaxError($e->getMessage());
        }

        $class = $ast[0] ?? null;
        $method = ($class instanceof Stmt\Class_) ? ($class->stmts[0] ?? null) : null;

        if (!$method instanceof Stmt\ClassMethod) {
            throw TranspileException::expectedSingleMethod();
        }

        return $method;
    }

    /**
     * @param Stmt[] $stmts
     */
    private function collectLocalVariables(array $stmts): void
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Stmt\Expression && $stmt->expr instanceof Expr\Assign) {
                $assignTarget = $stmt->expr->var;

                if ($assignTarget instanceof Expr\Variable && is_string($assignTarget->name)) {
                    $this->declaredLocals[$assignTarget->name] = true;
                }
            }

            if ($stmt instanceof Stmt\If_) {
                $this->collectLocalVariables($stmt->stmts);

                foreach ($stmt->elseifs as $elseif) {
                    $this->collectLocalVariables($elseif->stmts);
                }

                if ($stmt->else !== null) {
                    $this->collectLocalVariables($stmt->else->stmts);
                }
            }

            if ($stmt instanceof Stmt\While_) {
                $this->collectLocalVariables($stmt->stmts);
            }

            if ($stmt instanceof Stmt\For_) {
                foreach ($stmt->init as $initExpr) {
                    if ($initExpr instanceof Expr\Assign
                        && $initExpr->var instanceof Expr\Variable
                        && is_string($initExpr->var->name)
                    ) {
                        $this->declaredLocals[$initExpr->var->name] = true;
                    }
                }

                $this->collectLocalVariables($stmt->stmts);
            }

            if ($stmt instanceof Stmt\Foreach_) {
                if ($stmt->valueVar instanceof Expr\Variable && is_string($stmt->valueVar->name)) {
                    $this->declaredLocals[$stmt->valueVar->name] = true;
                }

                if ($stmt->keyVar instanceof Expr\Variable && is_string($stmt->keyVar->name)) {
                    $this->declaredLocals[$stmt->keyVar->name] = true;
                }

                $this->collectLocalVariables($stmt->stmts);
            }
        }
    }

    /**
     * @param Stmt[] $stmts
     */
    private function compileStatements(array $stmts): string
    {
        $code = '';

        foreach ($stmts as $stmt) {
            $code .= $this->compileStatement($stmt);
        }

        return $code;
    }

    private function compileStatement(Stmt $stmt): string
    {
        return match (true) {
            $stmt instanceof Stmt\Return_ => $this->compileReturn($stmt),
            $stmt instanceof Stmt\If_ => $this->compileIf($stmt),
            $stmt instanceof Stmt\While_ => $this->compileWhile($stmt),
            $stmt instanceof Stmt\For_ => $this->compileFor($stmt),
            $stmt instanceof Stmt\Foreach_ => $this->compileForeach($stmt),
            $stmt instanceof Stmt\Expression => $this->compileExpr($stmt->expr) . ";\n",
            default => throw TranspileException::unsupportedConstruct($stmt),
        };
    }

    private function compileReturn(Stmt\Return_ $stmt): string
    {
        return $stmt->expr === null
            ? "return;\n"
            : 'return ' . $this->compileExpr($stmt->expr) . ";\n";
    }

    private function compileIf(Stmt\If_ $stmt): string
    {
        $code = 'if (' . $this->wrapBool($this->compileExpr($stmt->cond)) . ") {\n";
        $code .= $this->compileStatements($stmt->stmts);
        $code .= '}';

        foreach ($stmt->elseifs as $elseif) {
            $code .= ' else if (' . $this->wrapBool($this->compileExpr($elseif->cond)) . ") {\n";
            $code .= $this->compileStatements($elseif->stmts);
            $code .= '}';
        }

        if ($stmt->else !== null) {
            $code .= " else {\n";
            $code .= $this->compileStatements($stmt->else->stmts);
            $code .= '}';
        }

        return $code . "\n";
    }

    private function compileWhile(Stmt\While_ $stmt): string
    {
        $code = 'while (' . $this->wrapBool($this->compileExpr($stmt->cond)) . ") {\n";
        $code .= $this->compileStatements($stmt->stmts);

        return $code . "}\n";
    }

    private function compileFor(Stmt\For_ $stmt): string
    {
        if (count($stmt->cond) > 1 || count($stmt->init) > 1 || count($stmt->loop) > 1) {
            throw TranspileException::unsupportedConstruct($stmt);
        }

        $init = $stmt->init === [] ? '' : $this->compileExpr($stmt->init[0]);
        $cond = $stmt->cond === [] ? '' : $this->wrapBool($this->compileExpr($stmt->cond[0]));
        $loop = $stmt->loop === [] ? '' : $this->compileExpr($stmt->loop[0]);

        $code = "for ({$init}; {$cond}; {$loop}) {\n";
        $code .= $this->compileStatements($stmt->stmts);

        return $code . "}\n";
    }

    private function compileForeach(Stmt\Foreach_ $stmt): string
    {
        if ($stmt->byRef) {
            throw TranspileException::unsupportedConstruct($stmt);
        }

        if (!$stmt->valueVar instanceof Expr\Variable) {
            throw TranspileException::unsupportedConstruct($stmt);
        }

        $code = 'for (const [__k, __v] of __phpEntries(' . $this->compileExpr($stmt->expr) . ")) {\n";

        if ($stmt->keyVar !== null) {
            if (!$stmt->keyVar instanceof Expr\Variable) {
                throw TranspileException::unsupportedConstruct($stmt);
            }

            $code .= $this->compileVariable($stmt->keyVar) . " = __k;\n";
        }

        $code .= $this->compileVariable($stmt->valueVar) . " = __v;\n";
        $code .= $this->compileStatements($stmt->stmts);

        return $code . "}\n";
    }

    private function compileExpr(Expr $expr): string
    {
        return match (true) {
            $expr instanceof Expr\Variable => $this->compileVariable($expr),
            $expr instanceof Expr\PropertyFetch => $this->compilePropertyFetch($expr),
            $expr instanceof Expr\ArrayDimFetch => $this->compileArrayDimFetch($expr),
            $expr instanceof Expr\Array_ => $this->compileArrayLiteral($expr),
            $expr instanceof Expr\Assign => $this->compileAssign($expr),
            $expr instanceof Expr\MethodCall => $this->compileMethodCall($expr),
            $expr instanceof Expr\PreInc => '++' . $this->compileExpr($expr->var),
            $expr instanceof Expr\PostInc => $this->compileExpr($expr->var) . '++',
            $expr instanceof Expr\PreDec => '--' . $this->compileExpr($expr->var),
            $expr instanceof Expr\PostDec => $this->compileExpr($expr->var) . '--',
            $expr instanceof Expr\BooleanNot => '!' . $this->wrapBool($this->compileExpr($expr->expr)),
            $expr instanceof Expr\BinaryOp\BooleanAnd => $this->compileBooleanOp($expr, '&&'),
            $expr instanceof Expr\BinaryOp\BooleanOr => $this->compileBooleanOp($expr, '||'),
            $expr instanceof Expr\BinaryOp\Concat => $this->compileConcat($expr),
            $expr instanceof Expr\BinaryOp\Plus => $this->compileBinaryOp($expr, '+'),
            $expr instanceof Expr\BinaryOp\Minus => $this->compileBinaryOp($expr, '-'),
            $expr instanceof Expr\BinaryOp\Mul => $this->compileBinaryOp($expr, '*'),
            $expr instanceof Expr\BinaryOp\Div => $this->compileBinaryOp($expr, '/'),
            $expr instanceof Expr\BinaryOp\Mod => $this->compileBinaryOp($expr, '%'),
            $expr instanceof Expr\BinaryOp\Identical => $this->compileBinaryOp($expr, '==='),
            $expr instanceof Expr\BinaryOp\NotIdentical => $this->compileBinaryOp($expr, '!=='),
            $expr instanceof Expr\BinaryOp\Smaller => $this->compileBinaryOp($expr, '<'),
            $expr instanceof Expr\BinaryOp\SmallerOrEqual => $this->compileBinaryOp($expr, '<='),
            $expr instanceof Expr\BinaryOp\Greater => $this->compileBinaryOp($expr, '>'),
            $expr instanceof Expr\BinaryOp\GreaterOrEqual => $this->compileBinaryOp($expr, '>='),
            $expr instanceof Expr\BinaryOp\Equal, $expr instanceof Expr\BinaryOp\NotEqual =>
                throw TranspileException::looseComparisonNotSupported($expr),
            $expr instanceof Scalar\Int_ => (string) $expr->value,
            $expr instanceof Scalar\Float_ => $this->compileFloat($expr->value),
            $expr instanceof Scalar\String_ => json_encode($expr->value, JSON_THROW_ON_ERROR),
            $expr instanceof Expr\ConstFetch => $this->compileConstFetch($expr),
            default => throw TranspileException::unsupportedConstruct($expr),
        };
    }

    private function compileVariable(Expr\Variable $expr): string
    {
        if (!is_string($expr->name)) {
            throw TranspileException::unsupportedConstruct($expr);
        }

        return $expr->name === 'this' ? 'this' : $expr->name;
    }

    private function compilePropertyFetch(Expr\PropertyFetch $expr): string
    {
        if (!$expr->name instanceof Node\Identifier) {
            throw TranspileException::unsupportedConstruct($expr);
        }

        return $this->compileExpr($expr->var) . '.' . $expr->name->toString();
    }

    private function compileArrayDimFetch(Expr\ArrayDimFetch $expr): string
    {
        if ($expr->dim === null) {
            throw TranspileException::unsupportedConstruct($expr);
        }

        return $this->compileExpr($expr->var) . '[' . $this->compileExpr($expr->dim) . ']';
    }

    /**
     * A PHP array literal is either a plain sequential list ([1, 2, 3],
     * compiled to a JS Array) or a purely string-keyed associative array
     * (["a" => 1], compiled to a JS Object) — see ADR 0013. Anything that
     * is neither (mixed keys, gaps, computed keys) is rejected rather than
     * guessed at.
     */
    private function compileArrayLiteral(Expr\Array_ $expr): string
    {
        $items = $expr->items;

        if ($items === []) {
            return '[]';
        }

        if ($this->isListLiteral($items)) {
            $values = array_map(
                fn (Node\ArrayItem $item): string => $this->compileExpr($item->value),
                $items,
            );

            return '[' . implode(', ', $values) . ']';
        }

        if ($this->isAssociativeLiteral($items)) {
            $pairs = array_map(function (Node\ArrayItem $item): string {
                assert($item->key instanceof Scalar\String_);

                return json_encode($item->key->value, JSON_THROW_ON_ERROR) . ': ' . $this->compileExpr($item->value);
            }, $items);

            return '{' . implode(', ', $pairs) . '}';
        }

        throw TranspileException::unsupportedArrayShape($expr);
    }

    /**
     * @param array<Node\ArrayItem|null> $items
     */
    private function isListLiteral(array $items): bool
    {
        foreach ($items as $index => $item) {
            if ($item === null || $item->unpack || $item->byRef) {
                return false;
            }

            if ($item->key === null) {
                continue;
            }

            if (!$item->key instanceof Scalar\Int_ || $item->key->value !== $index) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<Node\ArrayItem|null> $items
     */
    private function isAssociativeLiteral(array $items): bool
    {
        foreach ($items as $item) {
            if ($item === null || $item->unpack || $item->byRef || !$item->key instanceof Scalar\String_) {
                return false;
            }
        }

        return true;
    }

    private function compileMethodCall(Expr\MethodCall $expr): string
    {
        if (!$expr->name instanceof Node\Identifier) {
            throw TranspileException::unsupportedConstruct($expr);
        }

        $args = array_map(
            function (Node $arg) use ($expr): string {
                if (!$arg instanceof Node\Arg || $arg->name !== null || $arg->unpack) {
                    throw TranspileException::unsupportedConstruct($expr);
                }

                return $this->compileExpr($arg->value);
            },
            $expr->args,
        );

        return $this->compileExpr($expr->var) . '.' . $expr->name->toString() . '(' . implode(', ', $args) . ')';
    }

    private function compileAssign(Expr\Assign $expr): string
    {
        if ($expr->var instanceof Expr\ArrayDimFetch && $expr->var->dim === null) {
            throw TranspileException::arrayAppendNotSupported($expr);
        }

        $target = match (true) {
            $expr->var instanceof Expr\Variable => $this->compileVariable($expr->var),
            $expr->var instanceof Expr\PropertyFetch => $this->compilePropertyFetch($expr->var),
            $expr->var instanceof Expr\ArrayDimFetch => $this->compileArrayDimFetch($expr->var),
            default => throw TranspileException::unsupportedConstruct($expr),
        };

        return $target . ' = ' . $this->compileExpr($expr->expr);
    }

    private function compileBinaryOp(Expr\BinaryOp $expr, string $operator): string
    {
        return '(' . $this->compileExpr($expr->left) . ' ' . $operator . ' ' . $this->compileExpr($expr->right) . ')';
    }

    private function compileBooleanOp(Expr\BinaryOp $expr, string $operator): string
    {
        $left = $this->wrapBool($this->compileExpr($expr->left));
        $right = $this->wrapBool($this->compileExpr($expr->right));

        return '(' . $left . ' ' . $operator . ' ' . $right . ')';
    }

    private function compileConcat(Expr\BinaryOp\Concat $expr): string
    {
        $left = '__phpString(' . $this->compileExpr($expr->left) . ')';
        $right = '__phpString(' . $this->compileExpr($expr->right) . ')';

        return "({$left} + {$right})";
    }

    private function compileConstFetch(Expr\ConstFetch $expr): string
    {
        return match (strtolower($expr->name->toString())) {
            'true' => 'true',
            'false' => 'false',
            'null' => 'null',
            default => throw TranspileException::unsupportedConstruct($expr),
        };
    }

    private function compileFloat(float $value): string
    {
        return var_export($value, true);
    }

    private function wrapBool(string $jsExpr): string
    {
        return '__phpBool(' . $jsExpr . ')';
    }
}
