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
