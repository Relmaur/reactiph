<?php

declare(strict_types=1);

namespace Reactiph\Exception;

/**
 * Common contract every exception Reactiph throws implements. Lets calling
 * code catch "any Reactiph-originated failure" through one interface, and
 * gives both human developers and tooling (CI, editor integrations, an AI
 * agent parsing stderr) a structured way to read *why* something failed
 * beyond the prose message: a stable machine-readable code, structured
 * context data, and — where there's an unambiguous next step — a hint.
 */
interface ReactiphException extends \Throwable, \JsonSerializable
{
    /**
     * A stable, dot-namespaced identifier for this failure kind, e.g.
     * `template.unclosed_tag`. Stable across message wording changes, so
     * tooling can match on it instead of parsing prose.
     */
    public function code(): string;

    /**
     * A short, actionable next step for fixing the problem, or null when
     * the message is already the whole story.
     */
    public function hint(): ?string;

    /**
     * Structured data about the failure — offsets, names, expected vs.
     * actual — for tooling to consume without parsing the message string.
     *
     * @return array<string, mixed>
     */
    public function context(): array;
}
