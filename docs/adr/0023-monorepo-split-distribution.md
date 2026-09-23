# ADR 0023: Distribute `wordpress-bridge`/`taw-bridge` via a monorepo split, not separate repos or a private registry

- Status: accepted
- Date: 2026-09-23

## Context

`packages/wordpress-bridge` and `packages/taw-bridge` are only installable
today via Composer `path` repositories pointing at a local filesystem
checkout of this monorepo (ADR 0019's and ADR 0021's Consequences both
name this as a known gap). `taw/core` — the user's other framework,
already used as the model — is distributed as a plain Composer `vcs`
repository pointing at `https://github.com/Relmaur/taw-core` plus real
semver git tags; any `taw-theme` just adds that repository entry and a
version constraint, no local checkout or path repo needed. The ask: make
`reactiph/wordpress-bridge` and `reactiph/taw-bridge` installable the
same frictionless way.

The complication `taw/core` doesn't have: those two packages are
subdirectories of this monorepo, not their own repos, and Composer's
`vcs` repository type reads exactly one `composer.json` per repo/ref —
it can't see a nested package's `composer.json` from the parent repo's
tag.

## Decision

**Keep developing both packages inside this one monorepo** (unchanged —
still the right shape for active development: one PR can touch core,
`wordpress-bridge`, and `taw-bridge` together when a change spans all
three, as ADR 0021's own work did). **Add CI that mirrors each package
directory into its own standalone GitHub repo on every push to `main` and
every tag**, using `danharrin/monorepo-split-github-action`
(`.github/workflows/monorepo-split.yml`):

- `packages/wordpress-bridge` → `github.com/Relmaur/reactiph-wordpress-bridge`
- `packages/taw-bridge` → `github.com/Relmaur/reactiph-taw-bridge`

Both split repos are **public**, matching `taw-core`'s own visibility —
not because openness was a goal in itself, but because both packages'
`composer.json` require `reactiph/reactiph` (the core framework code),
so a private split repo would just move the friction this ADR exists to
remove: anyone installing a public `reactiph-taw-bridge` would still hit
an auth wall pulling in a private `reactiph/reactiph`. Making the split
repos public without also making `reactiph` itself public would have
been half a fix. `reactiph` (this repo's root) was flipped from private
to public for the same reason — it needs no split of its own, since its
`composer.json` already lives at the repo root and a plain `vcs`
repository pointing at it already works.

**The action mirrors current directory contents, not full git history.**
It clones the target repo, wipes its tree, copies the package directory's
present state, and makes one new commit per run (message derived from the
triggering commit's own first line) — not a `git subtree split`/`splitsh-lite`-style
rewrite that preserves per-file historical commits into the target repo.
Chosen because nothing here depends on that history surviving the split —
the target repos exist purely to be `composer install`'d, not read as a
development history in their own right — and the simpler tool has no
history-rewrite edge cases to get wrong.

**A fine-grained GitHub Personal Access Token**, scoped to just the two
target repos with `Contents: Read and write`, stored as this repo's
`ACCESS_TOKEN` Actions secret — the built-in `GITHUB_TOKEN` Actions
provides by default can't push to a *different* repository, so a real PAT
with cross-repo write access is unavoidable here. Created and added
directly through GitHub's own UI, never handled by an agent or pasted
into chat — a live credential passing through either would be a real,
unnecessary exposure.

## Alternatives considered

- **Split `wordpress-bridge`/`taw-bridge` into their own repos by hand,
  right now**, developing them there going forward instead of in this
  monorepo. Rejected: loses "one PR spans core + a bridge package" for
  changes that genuinely need that (ADR 0021's build did, touching core's
  `OwnMethods`/`ComponentTranspiler` alongside the new package in the same
  session) — real, current friction traded for a distribution problem that
  CI can solve without giving that up.
- **A private Composer registry (Packagist private, Satis, a self-hosted
  Toran Proxy-style server)** indexing multiple packages from one source
  without needing them to be separate git repos at all. Rejected as
  disproportionate infrastructure for two packages with one real
  consumer (`taw-theme`) so far — real hosting/auth to stand up and
  maintain, solving a problem a GitHub Action already solves for free.
- **`splitsh-lite`/`git subtree split`**, preserving full historical
  per-file commits in each split repo. Rejected for now: genuinely more
  correct if the split repos' own commit history ever matters (blame,
  bisecting a regression that shipped from the monorepo), but that's not
  a real need yet, and the tooling is more complex to wire correctly
  (needs the full unshallowed monorepo history available in CI, careful
  handling of merge commits). Revisit if the split repos' own history
  ever becomes something worth reading, not before.
- **Leaving the split repos private**, matching `reactiph`'s prior
  default. Rejected together with flipping `reactiph` itself public — see
  Decision above; a private split repo depending on a private core
  package doesn't solve the friction this ADR exists to remove.

## Consequences

- **`reactiph` (this repo) and both new split repos are now public on
  GitHub.** A real, visible change — confirmed with the user before
  either repo's visibility was touched, not assumed.
- **The split repos are CI-managed mirrors, not repos to develop in.**
  A change made directly in `reactiph-wordpress-bridge` or
  `reactiph-taw-bridge` (rather than in this monorepo's `packages/`
  directory) would be silently overwritten by the next split run — both
  repos' descriptions say so, but nothing yet enforces it technically
  (e.g., branch protection, a README banner in the split output itself).
- **`reactiph/reactiph`'s own installability still depends on
  `minimum-stability: dev`** wherever it's required, since it has no
  real semver tags yet (only `dev-main`) — this ADR doesn't fix that;
  tagging real releases is separate, still-open follow-on work. Once
  tagged, a tag pushed to this repo also tags both split repos with the
  same tag name (the workflow's tag-triggered step), so all three stay
  version-aligned automatically.
- **The `ACCESS_TOKEN` secret is a real, standing credential** with write
  access to two repos, living in this repo's Actions secrets — normal
  GitHub secret-rotation hygiene applies (revoke/reissue if ever
  suspected leaked), same as any other CI deploy credential.
- **A consuming project's `composer.json`** (e.g. `taw-theme`) can now
  replace its `path` repository entries for `wordpress-bridge`/`taw-bridge`
  with `vcs` entries pointing at the two split repos plus a version
  constraint — not done automatically by this ADR; updating `taw-theme`
  itself is separate, follow-on work.
