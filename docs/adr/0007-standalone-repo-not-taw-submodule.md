# ADR 0007: Standalone repo, not a TAW submodule

- Status: accepted
- Date: 2026-09-22

## Context

Reactiph originated out of work on TAW (the user's other project) and is
intended, among other things, as a fast-follow WordPress framework (ADR
0004) that TAW's own sites could eventually consume. It would have been
possible to build it as a submodule or subpackage inside the TAW
repository.

## Decision

Reactiph lives in its own standalone repository at `~/Documents/reactiph`,
independent of TAW's repo and git workflow. TAW's `AGENTS.md` commit/bump/
push rules and any other TAW-specific conventions do not apply here.

## Alternatives considered

- **TAW submodule/subpackage.** Rejected: Reactiph is a general-purpose
  framework, not TAW-specific tooling — coupling its release cadence,
  versioning, and commit conventions to TAW's would be an artificial
  constraint on a product meant to be usable (and eventually published)
  independently of TAW.

## Consequences

- Reactiph needs its own conventions from scratch (this ADR set, `CLAUDE.md`,
  its own composer package identity `reactiph/reactiph`) rather than
  inheriting TAW's.
- Consuming Reactiph from TAW (e.g. via the future `reactiph/wordpress-bridge`
  against a TAW WordPress site, per Part 7) happens through normal Composer
  dependency resolution, not a git submodule relationship.
- Any workflow guidance from the originating TAW session does not
  automatically carry over here unless explicitly re-stated for this repo.
