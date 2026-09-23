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
    public array $items = [];
    public array $labels = [];
    public bool $active = false;

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

    public function activeLabel(): string
    {
        return 'active: ' . $this->active;
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

    public function incrementA(): int
    {
        $this->a = $this->a + 1;

        return $this->a;
    }

    public function double(): int
    {
        return $this->a * 2;
    }

    public function quadruple(): int
    {
        return $this->double() + $this->double();
    }

    public function sumUpTo(): int
    {
        $i = 1;
        $total = 0;

        while ($i <= $this->a) {
            $total = $total + $i;
            $i++;
        }

        return $total;
    }

    public function countDownSteps(): int
    {
        $steps = 0;

        for ($i = $this->a; $i > 0; $i--) {
            $steps++;
        }

        return $steps;
    }

    public function listLiteral(): array
    {
        return [1, 2, 3];
    }

    public function assocLiteral(): array
    {
        return ['x' => 1, 'y' => 2];
    }

    public function firstItem(): mixed
    {
        return $this->items[0];
    }

    public function setFirstItem(): array
    {
        $this->items[0] = 99;

        return $this->items;
    }

    public function sumItems(): int
    {
        $total = 0;

        foreach ($this->items as $item) {
            $total = $total + $item;
        }

        return $total;
    }

    public function joinLabels(): string
    {
        $result = '';

        foreach ($this->labels as $key => $value) {
            $result = $result . $key . ':' . $value . ' ';
        }

        return $result;
    }
}
