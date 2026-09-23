# ADR 0013: PHP array duality — list-style → JS Array, associative → JS Object

- Status: accepted
- Date: 2026-09-22

## Context

PHP has exactly one array type, which is simultaneously an ordered list
and a string/int-keyed map. JS has two distinct types — `Array` and plain
`Object` — with no single type that's faithfully both. Transpiling PHP
array literals, access, and `foreach` requires picking a JS representation
for each concrete array shape at compile time, since `PhpToJs` has no
runtime type information to defer the decision with. This was flagged as
a materially different kind of complexity than what slices 1-2 (ADR
0011/0012) covered, and the user confirmed the approach below before
implementation rather than it being decided silently.

## Decision

**A PHP array literal compiles to one of two JS shapes, decided
structurally at compile time, per `PhpToJs::compileArrayLiteral()`:**

- **A plain sequential list** — every item either has no explicit key, or
  an explicit integer key that exactly matches its 0-based position
  (`[1, 2, 3]`, or the equivalent `[0 => 1, 1 => 2, 2 => 3]`) — compiles
  to a JS **Array** literal.
- **A purely string-keyed array** — every item has an explicit *string*
  key (`['a' => 1, 'b' => 2]`) — compiles to a JS **Object** literal.
- **Anything else** — mixed string/int keys, non-sequential or gapped
  integer keys, computed keys, spread (`...$x`), by-reference items — is
  rejected with `TranspileException::unsupportedArrayShape()`, not
  approximated. An empty literal `[]` defaults to a JS Array (matching
  how an empty PHP array behaves like a list until it's given its first
  key).

**Array access** (`$arr[$key]`, both read and write) compiles uniformly
to JS bracket notation (`arr[key]`) regardless of which of the two shapes
`arr` is — this works unchanged for both a JS Array (numeric index) and a
JS Object (string key), so no shape-dispatch is needed here. **Array
append** (`$arr[] = value`) is rejected outright
(`TranspileException::arrayAppendNotSupported()`): PHP's append always
picks the next integer key regardless of whether the array is otherwise
associative, which the compiler has no way to know or guarantee for an
arbitrary runtime value — silently guessing would risk exactly the
"looks right, wrong on some inputs" failure this project's allow-list
philosophy (ADR 0003) exists to avoid.

**`foreach` compiles to a plain inline `for (const [k, v] of
__phpEntries(arr))` loop**, using a new runtime helper
(`packages/runtime-js/php-runtime.js`) that returns `[key, value]` pairs
for either representation: `Array.isArray()` dispatches to `.map((v, i)
=> [i, v])` for a list, or `Object.keys()` for an associative one. This
is a **plain inline loop, not a callback-based helper** — the same
reasoning as slice 1's `let`-hoisting (ADR 0011): PHP's `foreach` loop
variables (and anything assigned inside its body) are function-scoped, so
wrapping the body in a JS callback function would silently break a
variable assigned inside the loop and read after it. `collectLocalVariables()`
now also registers a `foreach`'s key/value variable names, alongside
`if`/`while`/`for`'s hoisting.

## Alternatives considered

- **Always use a JS Object, keyed by string** (PHP array keys coerced to
  strings, matching how PHP itself stringifies numeric string keys).
  Rejected: throws away `Array.prototype` methods and native iteration
  order guarantees for the extremely common list case, and produces
  awkward output (`{"0": 1, "1": 2, "2": 3}`) for what's conceptually a
  plain list — bad ergonomics for the client-side code a future runtime
  (Part 5) needs to work with.
- **A single wrapper type** (e.g. a small `PhpArray` class in JS backed by
  a `Map`, uniformly used for every PHP array). Rejected: adds a
  non-native type every future JS-side consumer (Part 5's reactive
  runtime, any hand-written interop) would need to know about, for a
  problem the native Array/Object split already solves for the two
  shapes that actually occur in idiomatic PHP.
- **Runtime-dispatch instead of compile-time-dispatch for literals**
  (always emit some uniform constructor call, let a runtime helper decide
  Array vs. Object from the actual key values at execution time).
  Rejected: unlike `foreach` (where the array's shape genuinely isn't
  known until runtime, since it's a property value, not a literal), an
  array *literal*'s shape is fully known from its source text at compile
  time — deciding then is strictly more informative and produces cleaner,
  directly-inspectable output than deferring an already-knowable decision
  to a runtime call.

## Consequences

- A component method using an array in a way that doesn't fit either
  shape (mixed keys being the most likely real case, e.g. `['id' => 1,
  'Extra']`) gets a compile-time error today, not a runtime surprise —
  the author needs to restructure it into a plain list or a purely
  string-keyed array.
- `count()`, `array_map()`, `array_filter()`, and the rest of the ~15-20
  planned stdlib builtins (still unimplemented — see `docs/STATUS.md`)
  will need to account for both possible JS representations when they
  operate on arrays, since a builtin like `count()` can't itself know at
  compile time which shape a given array value has (unlike a literal).
  `__phpEntries()` is the first piece of exactly that kind of
  representation-bridging runtime code; expect more as builtins land.
- `packages/runtime-js/php-runtime.js` is no longer just the truthiness
  shim — it's now the general home for runtime support code transpiled
  output depends on. Anything loading transpiled component code (Part 5)
  must load this file first.
