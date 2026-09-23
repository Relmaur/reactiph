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
