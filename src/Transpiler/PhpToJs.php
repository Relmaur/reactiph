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
 * Supports an explicit, documented subset of PHP (ADR 0003): reading a
 * property (`$this->prop`) or local variable, arithmetic (`+ - * / %`),
 * strict comparison (`=== !==`), relational comparison (`< <= > >=`),
 * boolean operators (`&& || !`), string concatenation (`.`), local
 * variable assignment, `if`/`elseif`/`else`, and `return`. Anything else —
 * loops, arrays, method calls, property writes, loose comparison — throws
 * {@see TranspileException} rather than producing wrong JS. See ADR 0011
 * for why this particular slice, and for the PHP/JS semantic gaps
 * (truthiness, loose equality) it works around rather than ignores.
 *
 * PHP variables are function-scoped, not block-scoped, so every local
 * variable the method assigns anywhere is hoisted to a single `let`
 * declaration at the top of the generated JS function — this matters for
 * a variable assigned inside an `if` branch and read after it, which
 * would otherwise be out of scope in JS (`let` is block-scoped there).
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

    private function compileExpr(Expr $expr): string
    {
        return match (true) {
            $expr instanceof Expr\Variable => $this->compileVariable($expr),
            $expr instanceof Expr\PropertyFetch => $this->compilePropertyFetch($expr),
            $expr instanceof Expr\Assign => $this->compileAssign($expr),
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

    private function compileAssign(Expr\Assign $expr): string
    {
        if ($expr->var instanceof Expr\PropertyFetch) {
            throw TranspileException::propertyWriteNotSupported($expr);
        }

        if (!$expr->var instanceof Expr\Variable) {
            throw TranspileException::unsupportedConstruct($expr);
        }

        return $this->compileVariable($expr->var) . ' = ' . $this->compileExpr($expr->expr);
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
        return '(String(' . $this->compileExpr($expr->left) . ') + String(' . $this->compileExpr($expr->right) . '))';
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
