<?php

declare(strict_types=1);

namespace Reactiph\Tests\Transpiler\Fixtures;

/**
 * Real PHP methods used by both PhpToJsTest (transpiled-output shape) and
 * ParityTest (PHP-vs-transpiled-JS execution parity). Deliberately not a
 * BaseComponent subclass — PhpToJs transpiles a method in isolation,
 * independent of the Component/Template layer.
 */
final class ParityFixture
{
    public int $a = 0;
    public int $b = 0;
    public string $name = '';
    public string $flag = '';

    public function sum(): int
    {
        return $this->a + $this->b;
    }

    public function divide(): float
    {
        return $this->a / $this->b;
    }

    public function greeting(): string
    {
        return 'Hello, ' . $this->name . '!';
    }

    public function isEqual(): bool
    {
        return $this->a === $this->b;
    }

    public function isGreater(): bool
    {
        return $this->a > $this->b;
    }

    public function truthyAnd(): bool
    {
        return $this->flag && $this->name !== '';
    }

    public function negated(): bool
    {
        return !$this->flag;
    }

    public function branching(): string
    {
        if ($this->a > 10) {
            $result = 'big';
        } elseif ($this->a > 0) {
            $result = 'small';
        } else {
            $result = 'non-positive';
        }

        return $result;
    }
}
