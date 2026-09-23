# ADR 0015: Stdlib builtins — a deliberately small, checked allow-list

- Status: accepted
- Date: 2026-09-22

## Context

This was the last piece of the plan's original transpiler subset
(`Transpiler\Stdlib shims ~15-20 common PHP builtins to JS`). Naively
mapping a PHP builtin to its "obvious" JS equivalent is exactly how ADR
0014's boolean-concatenation bug happened — several of the most common
PHP string/array functions have real, easy-to-miss behavioral
differences from their apparent JS counterparts. Each candidate function
was checked against real PHP/JS behavior (empirically, `php -r` vs
`node -e`, the same method used to confirm ADR 0014's bug and ADR 0011's
truthiness gap) before being added, rather than assumed safe.

## Decision

**Ten builtins are supported**, dispatched by `PhpToJs::compileFuncCall()`
against `self::STDLIB_ARITY` (fixed arity per function, positional
arguments only): `count`, `strlen`, `array_key_exists`, `implode`,
`explode`, `trim`, `strtolower`, `strtoupper`, `str_replace`, and
`in_array` (handled separately — see below). Everything else throws
`TranspileException::unsupportedStdlibFunction()`. This is fewer than the
plan's "~15-20" estimate — deliberately: quality over hitting a number
(see "Deferred" below for what's missing and why).

**Real divergences found and worked around**, each with a runtime helper
in `packages/runtime-js/php-runtime.js` and a parity test that
specifically fails without the fix (verified: reverted the shim, confirmed
the "native" JS behavior actually diverges, via direct `php -r`/`node -e`
comparison, before trusting each fix):

- **`strlen()`**: PHP counts *bytes*; JS's `.length` counts UTF-16 code
  units. `"café".length` is 4 in JS, but PHP's `strlen("café")` is 5
  (the é is 2 UTF-8 bytes). `__phpStrlen()` uses `TextEncoder` to count
  actual UTF-8 bytes, matching PHP exactly.
- **`trim()`**: PHP's default character set is a fixed ASCII list (space,
  tab, newline, CR, null byte, vertical tab). JS's native `.trim()` also
  strips U+00A0 (non-breaking space) and other Unicode whitespace PHP's
  default does not. `__phpTrim()` uses a regex matching PHP's exact
  default set (the optional second `$characters` argument isn't
  supported).
- **`strtolower()`/`strtoupper()`**: PHP's default (no locale set) only
  touches ASCII `A-Z`/`a-z`. JS's native `.toLowerCase()`/`.toUpperCase()`
  are full Unicode-aware and would also transform accented/non-Latin
  characters PHP leaves untouched (`"ÀBC"` → PHP's `strtolower` gives
  `"Àbc"`; JS's `.toLowerCase()` gives `"àbc"`). `__phpStrtolower()`/
  `__phpStrtoupper()` use a regex touching only `[A-Za-z]`.
- **`implode()`**: stringifies each element with `__phpString()` (ADR
  0014), not native `String()` — otherwise it would inherit the same
  boolean-stringification bug that prompted ADR 0014 in the first place.

**`in_array()` requires an explicit, literal `true` third argument.** PHP's
default (`$strict = false`) uses loose comparison, which `PhpToJs` never
transpiles (ADR 0011) — calling with 2 args, or with a non-`true` (or
non-literal) third arg, throws `TranspileException::looseInArrayNotSupported()`.
Only `in_array($needle, $haystack, true)` compiles, to `__phpInArray()`.

**`count()`, `array_key_exists()`, `in_array()`, and `implode()` all
dispatch on the same Array/Object duality `__phpEntries()` already
established (ADR 0013)** — `Array.isArray()` to tell a list-style array
from an associative one at runtime, since a builtin operating on a
property value can't know its shape at compile time the way an array
*literal* can.

**Deferred, not attempted**: `array_map()`/`array_filter()` need a
callable argument (a closure or `[$this, 'method']` array-callable) —
`PhpToJs` has no closure support at all yet, so these would need that
built first, which is real scope beyond "add a builtin." `sprintf()`
needs a genuine format-string mini-language parser (width, padding,
precision, type specifiers) with no native JS equivalent — implementing a
*partial* one under time pressure risked exactly the "looks right, wrong
on some inputs" failure this whole approach exists to avoid, so it's left
out entirely rather than half-built.

## Alternatives considered

- **Map every builtin 1:1 to its apparent JS equivalent** (`strlen` →
  `.length`, `trim` → `.trim()`, etc.), matching the plan's "~15-20
  shims" more literally and quickly. Rejected outright once the first
  divergence (`strlen`) was checked and confirmed real — this is
  precisely the failure mode ADR 0014 had just been a live example of.
- **Implement a restricted, literal-format-string-only `sprintf`** (e.g.
  only `%s`/`%d`, no width/padding). Considered, rejected for this pass:
  still meaningfully more design surface than the other builtins (needs
  its own parser, its own allow-list of supported specifiers, its own
  rejection path for unsupported ones), better done as a focused
  follow-up than bundled in under an already-large slice.

## Consequences

- A component method using `array_map`, `array_filter`, `sprintf`, or any
  function outside the ten supported ones gets a clear compile-time error
  naming exactly which function isn't supported
  (`transpiler.unsupported_stdlib_function`), not a silent no-op or wrong
  output.
- `implode()`/`count()`/`array_key_exists()`/`in_array()` all correctly
  handle a property that turns out to be either array shape at runtime —
  but note this means these builtins' *codegen* is representation-agnostic
  even though array *literals* (ADR 0013) resolve their shape at compile
  time; the two are deliberately different because a literal's shape is
  known from source text and a property's isn't.
- Closures remain entirely unimplemented — needed for `array_map`/
  `array_filter` (and likely other things, e.g. any future `usort`-style
  builtin). If Part 5 or later work needs closures for another reason,
  revisit `array_map`/`array_filter` at the same time rather than
  building closure support twice.
- This closes out Part 4's original scope as planned (ADR 0011 → 0012 →
  0013 → 0014 → 0015). Worth a final report back to the user before
  starting Part 5, per the working agreement.
