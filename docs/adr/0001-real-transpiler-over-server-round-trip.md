# ADR 0001: Real PHP→JS transpiler, not a server-round-trip reactivity model

- Status: accepted
- Date: 2026-09-22

## Context

There are two well-established ways to make server-rendered PHP components
reactive in the browser without a separate frontend framework:

1. **Server round-trip** (Livewire/Alpine style): the browser sends an AJAX
   request on every interaction; the server re-renders and diffs the
   component, and the client patches the DOM from the response. No PHP
   ever runs in the browser.
2. **Real transpilation** (Viewi's actual mechanism): the component's PHP
   is compiled to JS ahead of time, so the *same* component logic runs
   server-side for SSR and client-side for reactive updates, with no
   network round-trip per interaction.

Option 1 is dramatically simpler to build and ships faster. Option 2 is
what Viewi (the project Reactiph is modeled on) actually does, and is the
harder, riskier path.

## Decision

Build the real transpiler (option 2). This was discussed explicitly with
the user and chosen deliberately over the simpler alternative.

## Alternatives considered

- **Server round-trip.** Rejected: it was the "easy way out" and produces
  a fundamentally different (and less capable) product — every interaction
  costs a network request, and the framework can't work offline or under
  poor connectivity. The user wants Reactiph to be a real peer to Viewi's
  approach, not a Livewire clone.

## Consequences

- Commits the project to Part 4 (the transpiler) as explicitly the
  highest-risk part of the build — see the build order in `CLAUDE.md`.
- Requires a documented, enforced subset of PHP (ADR 0003) and a stdlib
  shim, since JS can't run arbitrary PHP.
- Requires parity testing (ADR 0006) as the only reliable way to trust the
  transpiler's output incrementally.
- Justifies decoupling hydration-protocol risk from transpiler risk
  (ADR 0005) — this is a materially harder build than option 1 and needs
  its risks isolated rather than compounded.
