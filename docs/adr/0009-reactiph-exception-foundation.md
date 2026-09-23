# ADR 0009: A common, structured exception foundation — established now, applied going forward

- Status: accepted
- Date: 2026-09-22

## Context

By the end of Part 2, three exception classes existed (`ParseException`,
`UnknownComponentException`, and a raw `\InvalidArgumentException` thrown
from `ComponentRegistry`), each built ad hoc with `new Xyz(sprintf(...))`
and a prose message only. That's already inconsistent, and every
subsequent part adds more failure modes — Part 4's transpiler in
particular is *explicitly* required (ADR 0003) to fail loudly and clearly
at compile time rather than produce silently-wrong JS, which makes
exception quality a correctness requirement, not just polish. The request
that prompted this ADR was explicitly framed as wanting error handling
that's usable by both human developers and AI agents/tooling — a prose-only
message string serves neither well: humans have to re-read it under
pressure, and tooling has nothing to parse but English.

The question this ADR answers isn't "should Reactiph have good errors" —
obviously yes — it's *when*: retrofit later once many more exception types
exist across Parts 3-8, or establish the shape now while there are only
three call sites to touch.

## Decision

Establish the foundation now, retrofit the three existing exceptions onto
it, and treat it as a standing convention every future part follows — not
a one-time "Part" of its own, the way parity testing (ADR 0006) is a
standing convention rather than a single deliverable.

The foundation: a `ReactiphException` interface (`src/Exception/`) that
every framework exception implements, on top of whatever SPL exception
class is semantically correct for it (`RuntimeException`,
`InvalidArgumentException`, `LogicException`, ...). It adds three things
beyond the standard `getMessage()`:

- `code(): string` — a stable, dot-namespaced identifier (e.g.
  `template.missing_closing_tag`) that doesn't change when message wording
  does, so tooling can match on failure *kind* instead of parsing prose.
- `hint(): ?string` — a short, actionable next step, when there is an
  unambiguous one (there often isn't for a genuine bug, which is fine —
  `null` is honest).
- `context(): array` — structured data about the failure (offsets, tag
  names, expected-vs-found) that a human or a tool can read directly
  instead of extracting it from a formatted string.

`ReactiphException` also extends `\JsonSerializable`, so
`json_encode($exception)` gives `{code, message, hint, context}` — a
concrete, minimal "AI-friendly" hook: anything that catches a
`ReactiphException` (a CI job, an editor integration, an agent parsing a
failed `php examples/*.php` run) gets structured data without scraping a
stack trace.

Storage/accessor boilerplate lives in a `CarriesDiagnostics` trait, used by
each concrete exception class. Every concrete exception exposes one named
static constructor per distinct failure case (e.g.
`ParseException::missingClosingTag('div')`) that fills in code/hint/context
— never `new ParseException($message)` directly. `Parser` now has eleven
such cases; `ComponentRegistry` two (`UnknownComponentException::forTag()`,
the new `InvalidComponentException::mustExtendBaseComponent()`); `Compiler`
one (`CompilerException::unknownNodeType()`, for the internal-invariant
case that's a Reactiph bug, not a template-author mistake, if it's ever
hit).

## Alternatives considered

- **Defer this until more exception types exist**, on the theory that the
  right shape is clearer with more examples. Rejected: the cost only grows
  — three call sites today become dozens across Parts 3-8, most of them in
  the highest-risk part (the transpiler), which is exactly where crisp
  errors matter most and where retrofitting under time pressure is worst.
- **A single concrete `ReactiphException` base class** instead of an
  interface + trait. Rejected: forces every framework exception into the
  same SPL parent (`RuntimeException`, say), which is semantically wrong
  for cases that are genuinely `InvalidArgumentException` or
  `LogicException` — callers doing narrow catches on SPL types (a
  reasonable, common pattern) would lose that signal.
- **Free-form `context()` only, no `code()`.** Rejected: a message-parsing
  or context-shape-sniffing integration is exactly the brittle pattern a
  stable code exists to avoid. The code is the part that's safe to depend
  on across message-wording changes.
- **Override `__toString()` to hide the stack trace behind a formatted
  block.** Rejected: the default `Exception::__toString()` stack trace is
  still what a human debugging in a terminal needs most; `jsonSerialize()`
  is the structured, tooling-facing view, not a replacement for it.

## Consequences

- Every future exception class, in every future part, implements
  `ReactiphException` and is constructed only via a named static
  constructor. This is now a checklist item alongside "did you add a
  parity test" (ADR 0006) and "did you update `docs/STATUS.md`."
- Part 4's transpiler diagnostics (unsupported PHP construct, disallowed
  stdlib call) get this shape by default rather than needing their own
  design pass — that's the main payoff this ADR is banking on.
- `code()` strings are now part of the framework's informal public
  surface, the same way message wording informally was before — renaming
  one is a breaking change for anything that matches on it, even though
  nothing enforces that today.
- The `CarriesDiagnostics` trait's `withDiagnostics()` is `private`, so a
  concrete exception's named constructors are the only way to set
  diagnostics — this is deliberate friction to keep `new Xyz($message)`
  from creeping back in.
