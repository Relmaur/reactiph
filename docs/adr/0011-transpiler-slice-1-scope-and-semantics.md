# ADR 0011: Transpiler slice 1 — scope, and how it handles PHP/JS semantic gaps

- Status: accepted
- Date: 2026-09-22

## Context

Part 4 (the PHP→JS transpiler) is explicitly flagged in the build plan as
the highest-risk part, with an instruction to treat it as a timeboxed
spike and confirm scope is holding before continuing. Asked the user how
they wanted that checkpoint to work rather than assuming; they chose: build
a small core subset first, prove the whole pipeline end-to-end, then stop
and report before expanding further. This ADR covers what that first
slice is, and — more importantly — several places where naively mapping a
PHP construct to its "obvious" JS equivalent would have been silently
wrong, which the transpiler avoids on purpose.

## Decision

**Slice 1 scope**, implemented in `Transpiler\PhpToJs::transpileMethod()`:
reading a property (`$this->prop`) or local variable, arithmetic
(`+ - * / %`), **strict** comparison (`=== !==`), relational comparison
(`< <= > >=`), boolean operators (`&& || !`), string concatenation (`.`),
local variable assignment, `if`/`elseif`/`else`, and `return`. Everything
else from the plan's full documented subset — loops, arrays,
`$this->method()` calls, property *writes* — is explicitly out of scope
for this slice and throws `TranspileException` (per ADR 0003's allow-list
philosophy) rather than being silently unsupported.

**Loose comparison (`==`/`!=`) is rejected, not transpiled.** PHP's loose
equality and JS's loose equality diverge for some inputs (their type
coercion rules aren't the same), and getting this wrong is exactly the
"silently produces different behavior in the browser" failure mode ADR
0001/0003 exist to prevent. Rather than attempt to replicate PHP's loose
comparison table in JS (real, but substantially more complex, and not
needed by the demo methods this slice targets), `PhpToJs` rejects `==`/`!=`
outright with a hint to use `===`/`!==` instead.

**PHP truthiness is replicated via a runtime shim, not JS's native
truthiness.** `if`, `&&`, `||`, and `!` all coerce their operand(s) to a
boolean. PHP and JS disagree on this for some values — most notably, the
*string* `"0"` is falsy in PHP but truthy in JS (JS only treats `""` as a
falsy string). Transpiled code never relies on JS's native truthiness for
these operators; every boolean-context operand is wrapped in a
`__phpBool()` call (`packages/runtime-js/php-runtime.js`) that replicates
PHP's actual truthiness rules. `ParityTest`'s two "truthiness" cases exist
specifically to prove this — they use `flag = "0"` and would fail if
`__phpBool` were removed and JS's native operators used instead.

**Local variables are hoisted to one `let` at the top of the generated
function.** PHP variables are function-scoped, not block-scoped — a
variable assigned inside an `if` branch remains readable after the
`if`/`else` ends. JS's `let` is block-scoped, so naively emitting
`let x = "big";` *inside* the `if` block would make `x` inaccessible in a
`return x;` that follows the whole `if`/`else`. `PhpToJs` first walks the
method body to collect every locally-assigned variable name, emits them
all as one `let a, b, c;` at the top of the function, and every actual
assignment becomes a plain `x = ...;` with no `let` — correctly matching
PHP's function-scoping regardless of which nested block the assignment
happens in. `PhpToJsTest::testHoistsLocalVariablesAssignedInsideABranchToTheTopOfTheFunction()`
and the parity suite's `branching` cases exercise this directly.

**String concatenation stringifies both operands via JS's `String()`.**
This matches PHP's `.` operator closely for the types this slice actually
supports (ints, strings, bools) — `ParityTest`'s `greeting` case covers
this. Float-to-string formatting is a known area where PHP and JS *can*
diverge (edge cases like very large/small magnitudes using different
exponential-notation thresholds) — not covered by this slice's parity
tests, since no supported construct here produces such a float, but worth
remembering before assuming string concat is unconditionally safe once
floats are exercised more broadly.

**Node is now a required CI dependency**, not just a local one — added
`actions/setup-node@v4` to `.github/workflows/ci.yml`, since the parity
suite (`ParityTest`, using the test-only `NodeRunner` helper) shells out to
a real `node` binary and would otherwise fail in CI.

## Alternatives considered

- **Replicate full PHP loose-equality semantics in JS.** Rejected for this
  slice: real complexity for a comparison operator none of the target demo
  methods need, when strict comparison already covers the realistic case
  (component methods comparing known-typed properties). Revisit if a
  concrete need for loose comparison emerges.
- **Rely on JS's native `&&`/`||`/`!`/`if` truthiness and accept the
  divergence as a documented limitation** instead of building
  `__phpBool()`. Rejected: this is precisely the "looks fine, breaks on
  specific inputs in production" failure mode the project's allow-list
  philosophy (ADR 0003) exists to avoid, and the fix (one small runtime
  function) is cheap relative to the risk.
- **Emit `var` instead of hoisted `let`,** relying on `var`'s own
  function-scoping (which would have solved the branch-scoping problem
  without a manual collection pass). Rejected: `var` also hoists but
  additionally allows redeclaration and has looser TDZ behavior than
  `let`; explicit collection-and-hoist keeps the generated code's
  intent obvious and doesn't rely on a quirk of `var` that's typically
  discouraged in modern JS.

## Consequences

- Property *writes* (`$this->prop = ...`), method calls, loops, and arrays
  remain unimplemented — realistic component methods that need them can't
  be transpiled yet. This is deliberate (the approved checkpoint), not an
  oversight; `docs/STATUS.md` tracks it as the immediate next slice.
- `__phpBool()` (and any future stdlib shims) must ship as part of
  whatever loads transpiled component code in the browser — Part 5's
  hydration/reactive runtime needs to guarantee `packages/runtime-js/php-runtime.js`
  loads before any transpiled component code executes.
- The parity suite is now load-bearing for trusting the transpiler
  incrementally (ADR 0006) — any future addition to the supported subset
  needs a parity case proving it, especially wherever PHP and JS might
  quietly disagree (the truthiness case here being the concrete
  precedent for what that kind of test should look like).
- CI now requires Node in addition to PHP — a contributor without Node
  locally can still run `composer analyse`/`cs-check`, but `composer test`
  (and therefore `composer check`) requires it.
