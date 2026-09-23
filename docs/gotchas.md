# Gotchas

A running log of non-obvious pitfalls, workarounds, and hard-won lessons
discovered while building Reactiph. This exists so a future session (human
or agent) doesn't have to rediscover them from scratch. Add an entry
whenever something surprising costs more than a few minutes to figure out —
append, don't rewrite history, and prune only when the underlying code
changes in a way that makes an entry stop applying (say so in the entry
rather than deleting silently).

## Part 1 — Template/Compiler

### The compiled render closure is cached per class, so it can't bind `$this` at compile time

`Compiler::compile()` returns one closure per component *class*, cached in
`BaseComponent::$rendererCache` and reused across every instance of that
class. That means the closure can't be permanently bound to one component
via `Closure::bindTo()` at compile time — the next instance to render would
get the wrong `$this`. Instead, `BaseComponent::render()` invokes it with
`$renderer->call($this, $this)`, which binds `$this` for that single call
only. This is what makes `{$this->method()}` work in templates while still
sharing one compiled closure across many instances.

### `extract(get_object_vars($component))` only sees *public* properties

`get_object_vars()` called from outside the object's class only returns
public properties (PHP's normal visibility rules apply to reflection-ish
calls like this). This is actually what we want — component state is
documented as "public properties," and non-public properties are
correctly invisible to templates — but it's easy to be surprised if you
add a `protected`/`private` property and wonder why `{$expr}` can't see it.

### Off-by-one in "did we find the closing tag" detection

`Parser::parseNodes()` originally inferred "we exited the loop because we
ran out of input" by checking `$this->pos >= $this->length` after the loop.
That's wrong when the closing tag is the very last thing in the input —
consuming it correctly advances `pos` to exactly `length`, which the old
check misread as "ran out of input before finding it." Fixed by tracking
an explicit `$foundClosingTag` bool instead of inferring intent from
position. Lesson: don't infer *why* a loop exited from a side effect that
a *successful* exit can also produce.

### Anonymous test-double components need `#[\AllowDynamicProperties]`

PHP 8.2+ deprecates assigning a property that isn't declared on the class
(unless the class is marked `#[\AllowDynamicProperties]` or extends
something like `stdClass`). Test doubles built as `new class (...) extends
BaseComponent` that set arbitrary `$props` via `$this->{$key} = $value`
need the attribute, or PHPUnit surfaces deprecation noise. Real components
should still declare their state as typed properties — this only applies
to throwaway test fixtures that assign properties dynamically by design.

### `nikic/php-parser` is already in `vendor/` — but only as PHPUnit's dependency

`composer show` will list `nikic/php-parser` after `composer install`
because PHPUnit's code-coverage driver depends on it transitively. Don't
mistake that for Reactiph having a direct dependency on it yet — Part 4
(ADR 0002) needs to add it explicitly to `composer.json`'s `require`, not
assume it's already a project dependency because it happens to be on disk.

## Part 2 — Component tree

### Void elements were still getting a closing tag emitted

The original `Compiler::compileHtmlTag()` always emitted `<tag>...children...</tag>`,
so a void element like `<img src="a.png">` (empty children, parsed without
a matching `</img>`) rendered as `<img src="a.png"></img>` — invalid HTML.
The bug: nothing in the AST distinguished "self-closing/void, no closing
tag exists" from "a normal tag that just happens to have no children," e.g.
`<div></div>`. Fixed by adding an explicit `TagNode::$selfClosing` bool set
by the parser at the three points it produces a self-closed/void node,
which the compiler checks before deciding whether to emit a closing tag.
Caught by `ComponentTreeTest`'s end-to-end render, not a narrower unit
test — worth remembering that AST-shape bugs like this often only surface
once something exercises the full parse→compile→render path.

### `CarriesDiagnostics` declares `abstract public function getMessage()` defensively, not because PHPStan requires it

`CarriesDiagnostics::jsonSerialize()` calls `$this->getMessage()`, which
the trait itself doesn't define — it's inherited from whatever SPL
exception class (`\RuntimeException`, etc.) the using class extends.
Verified empirically (temporarily removed the `abstract` declaration and
re-ran `composer analyse`) that PHPStan (level 8) is actually fine either
way here — it resolves `getMessage()` through the concrete classes that
use the trait. Kept the `abstract` declaration anyway as documentation of
the trait's implicit contract (it only works when the host class extends
`\Throwable`), not as a fix for a real analysis failure. Don't assume this
generalizes to every trait/host-method situation — re-verify rather than
assume PHPStan needs (or doesn't need) an abstract declaration elsewhere.

## Part 3 — Hydration

### `chrome-devtools-mcp`'s browser profile is shared across every session on the machine, and simultaneous sessions collide

`chrome-devtools-mcp` launches Chrome against a fixed, single profile
directory (`~/.cache/chrome-devtools-mcp/chrome-profile`) regardless of
which project or Claude session invokes it. If another session on the
same machine already has a browser open against that profile,
`new_page`/`list_pages` fail outright with "The browser is already
running for ... Use a different `userDataDir`" — there's no way for a
second session to attach to a browser a *different* chrome-devtools-mcp
server process launched (it uses a private debugging pipe to its own
child process, not a shared TCP CDP endpoint), and killing that Chrome
process risks interrupting whatever the other session is doing with it —
checked `ListAgents` and found another session showing `busy`, so did not
assume it was safe to kill.

**Workaround used**: `chrome-devtools-mcp`'s own npm cache already has
`puppeteer-core` installed (found via `find ~/.npm/_npx -iname
puppeteer-core`); required it directly from a standalone Node script with
a fresh, isolated `userDataDir` (in the scratchpad directory) and an
explicit `executablePath` pointing at the real Chrome binary. This gets
real headless-browser verification (navigate, click, read the DOM back)
with zero risk of touching another session's browser and no new
downloads, at the cost of writing the interaction script by hand instead
of using the `chrome-devtools-mcp` tool calls. Reuse this pattern rather
than fighting the shared-profile lock, unless you've confirmed no other
session has the shared browser open (e.g. every peer in `ListAgents` shows
`idle`).

## Part 4 — Transpiler

### `dirname(__DIR__, N)` — count directory levels from `__DIR__` itself, not from the file

In `tests/Transpiler/Support/NodeRunner.php` (three levels below the repo
root: `tests/Transpiler/Support/`), first wrote `dirname(__DIR__, 4)` to
reach the repo root and got "could not read
packages/runtime-js/php-runtime.js" — off by one. `__DIR__` there is
already `.../tests/Transpiler/Support`, so `dirname(__DIR__, 1)` is
`.../tests/Transpiler`, `dirname(__DIR__, 2)` is `.../tests`, and
`dirname(__DIR__, 3)` is the repo root — three hops, not four. The mistake
was mentally counting the file's own path segments (`tests/Transpiler/
Support/NodeRunner.php` — 4 segments including the filename) instead of
counting `dirname()` calls *starting from the directory `__DIR__` already
is*, which is one hop shorter. Worth double-checking with a quick
`var_dump(dirname(__DIR__, N))` (or just running the test and reading the
error) rather than trusting mental arithmetic here.

### A parity test for a *mutating* method must snapshot JS state before, not after, calling the real PHP method

`ParityTest`'s `incrementA` case (property write: `$this->a = $this->a + 1;
return $this->a;`) failed with the JS side returning 6 where PHP returned
5, for `a` starting at 4. Not a transpiler bug — a test-harness ordering
bug: the test built the JS context with `get_object_vars($fixture)`
*after* calling `$fixture->incrementA()`, by which point PHP had already
mutated `$fixture->a` from 4 to 5. So the JS side started from the
already-incremented value and produced 6. Fixed by capturing
`get_object_vars($fixture)` *before* calling the PHP method under test.
General lesson: once the transpiler supports property writes, every
parity case for a mutating method needs its "before" state snapshotted
before the PHP call, not assumed to still match the data provider's input
array (which itself doesn't get mutated — the object does).

### Passing parity tests only prove parity for the types they actually exercised

`compileConcat()` shipped in slice 1 using JS's native `String()` for
both operands. `String(true)` is `"true"` in JS; PHP's own boolean cast
gives `"1"`. Slice 1's parity case for concatenation (`greeting`) only
ever passed a *string* property (`name`) through it — it never exercised
a boolean operand, so the divergence shipped silently and stayed shipped
across slices 2 and 3 until noticed while implementing an unrelated
builtin (`implode()`, which needed the same stringification logic). Fixed
in ADR 0014 with a `__phpString()` runtime helper and two new parity
cases using a real `bool` fixture property. Lesson: "this construct has a
passing parity test" only proves parity for the specific *types* that
test happened to pass through it — when adding a new construct (or
revisiting an old one), check it against every type in the fixture's
property set that could plausibly reach it, not just whichever one the
first test case reached for.

### PHPStan narrows a `match` through a preceding `isset()` check on the same key set

`compileFuncCall()`'s `match ($name) { 'count' => ..., ..., 'str_replace'
=> ... }` had a `default => throw ...` arm as a safety net. PHPStan flagged
the *last* real arm ('str_replace') as "comparison... is always true" and
the `default` as unreachable — not a bug, but a real, useful observation:
an earlier `if (!isset(self::STDLIB_ARITY[$name])) { throw ...; }` already
proves, by the time the `match` runs, that `$name` is one of
`STDLIB_ARITY`'s exact keys, and the `match` lists all of those keys — so
`default` genuinely can never execute. Removed it, with a comment
explaining PHP's own `UnhandledMatchError` is the fallback if the arity
table and the match arms ever drift out of sync. Lesson: PHPStan tracks
type narrowing across an `isset()` on a `const array` and will flag a
`match`'s `default` as dead code once earlier control flow has already
proven exhaustiveness — worth checking for this pattern specifically
before assuming a "just in case" default arm is free insurance.

## Part 5 — Reactive client runtime

### A bare template variable (`{$count}`) and a bare method-body variable (`$count = 1;`) mean different things to the transpiler

Reused `PhpToJs::compileExpr()`'s existing `compileVariable()` to transpile
template `{$expr}` markers for DOM patching (ADR 0017), assuming it would
just work since it's the same PHP expression syntax. It compiled `{$count}`
to a bare `count` identifier — which throws/`undefined`s client-side,
since the JS expression thunk has no local `count`, only `this` (bound via
`.call(instance)`). The bug: `{$count}` in a template is sugar for
`extract(get_object_vars($component))`'s extracted property (real, running
PHP, server-side only) — but inside a transpiled *method body*, a bare
`$count` genuinely is a local variable, and `compileVariable()` was built
for that case first. Caught by actually running `php examples/hydrate.php`
and reading the emitted JS by eye before trusting it (not by a failing
test — none existed yet for this path), the same "verify empirically, on
the actual boundary you're about to build on, not just where you'd
normally look" habit `__phpString()` (ADR 0014) came from. Fixed with a
mode flag (`$inTemplateExpression`) so `transpileExpression()` treats
every bare non-`$this` variable as a `this.` property access, provably
safe only because the transpiler's allow-listed subset (ADR 0011) has no
closures/arrow functions, so a template expression can never actually
contain a real local variable under that subset.

## `reactiph/taw-bridge`

### Merely autoloading a `taw/core` class hangs the process if `ABSPATH` isn't defined first — it doesn't fail loudly

Building `packages/taw-bridge`'s test suite, `php vendor/bin/phpunit` (and
even a bare `php -r "class_exists(\TAW\Core\Block\MetaBlock::class);"`)
hung indefinitely — no output, no error, no PHPUnit progress dots, just
silence past any reasonable timeout. Every `taw/core` source file starts
with WordPress's standard "block direct file access" guard,
`if (!defined('ABSPATH')) { exit; }` — with `ABSPATH` undefined (no real
WordPress bootstrap in a plain PHPUnit process), that line runs the
instant the class is autoloaded, before any of this package's own code
gets a chance to execute. The genuinely surprising part: this did **not**
show up as a clean, fast `exit` — from the outside it looked exactly like
an infinite hang (confirmed by first proving the *same* `class_exists()`
call completes instantly once `ABSPATH` is defined first: identical code,
only the timing changed). The exact mechanism for why an autoloaded
`exit()` presented as a hang rather than an immediate clean termination
wasn't tracked down further — not worth the time once the empirical fix
was confirmed to work reliably, and re-verify before trusting a similar
assumption elsewhere rather than assuming this generalizes.

**Fix**: define `ABSPATH` (to any placeholder path) at the very top of
`tests/bootstrap.php`, before `vendor/autoload.php` is even required —
`reactiph/wordpress-bridge`'s own bootstrap never needed this, since none
of *its* hand-rolled stubs are the files being guarded; here, the guarded
files are real, unmodified `taw/core` source being autoloaded directly.
Worth checking for this exact guard pattern (`if (!defined('ABSPATH'))`)
in any other real WordPress-ecosystem package pulled in via a path
repository for testing, not just `taw/core` specifically.
