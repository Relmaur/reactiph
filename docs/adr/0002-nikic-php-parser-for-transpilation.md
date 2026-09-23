# ADR 0002: Use nikic/php-parser for the PHP→JS transpiler

- Status: accepted (not yet implemented — targets Part 4)
- Date: 2026-09-22

## Context

Transpiling PHP to JS (ADR 0001) requires parsing PHP into an AST first.
Hand-rolling a PHP parser is a large, error-prone undertaking on its own —
PHP's grammar has plenty of sharp edges (string interpolation forms,
heredoc/nowdoc, operator precedence, etc.).

## Decision

Use [nikic/php-parser](https://github.com/nikic/php-parser) — a mature,
widely-used PHP parser (also what PHPStan, Psalm, Rector, and PHPUnit's own
tooling are built on) — as the parser for `Transpiler\PhpToJs`, rather than
writing one from scratch.

## Alternatives considered

- **Hand-rolled PHP parser.** Rejected: this is the single biggest
  avoidable scope expansion in the whole project. It would turn "build a
  transpiler for a documented PHP subset" into "build a correct PHP parser,
  then also a transpiler," for no benefit — nikic/php-parser already
  produces a well-documented, stable AST.
- **Reflection-based transpilation** (inspect compiled PHP via Reflection
  instead of parsing source). Rejected: Reflection exposes structure and
  types, not executable statement bodies — it can't give us the method body
  AST we need to emit equivalent JS.

## Consequences

- `Transpiler\PhpToJs` depends on nikic/php-parser as a direct runtime
  dependency starting in Part 4 (it's currently only present as a
  transitive dev-dependency of PHPUnit — see `docs/gotchas.md`).
- The transpiler's supported PHP subset (ADR 0003) is defined in terms of
  nikic/php-parser's `Node\*` types, which shapes how `PhpToJs` is
  structured (a visitor/dispatcher per node type is the natural fit).
- We inherit nikic/php-parser's PHP version support and quirks; version
  pinning for that package should track our own `php` composer constraint.
