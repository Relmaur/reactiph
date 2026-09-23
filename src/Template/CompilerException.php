<?php

declare(strict_types=1);

namespace Reactiph\Template;

use Reactiph\Exception\CarriesDiagnostics;
use Reactiph\Exception\ReactiphException;

/**
 * Thrown for internal invariant violations in {@see Compiler} — cases that
 * indicate a bug in Reactiph itself (e.g. a {@see Parser} output type the
 * compiler doesn't know how to handle), not a mistake in user-authored
 * template markup. Contrast with {@see ParseException}, which is always
 * the template author's mistake.
 */
final class CompilerException extends \LogicException implements ReactiphException
{
    use CarriesDiagnostics;

    public static function unknownNodeType(string $nodeClass): self
    {
        $e = new self(sprintf('Unknown template AST node type: %s.', $nodeClass));

        return $e->withDiagnostics(
            'template.unknown_node_type',
            ['class' => $nodeClass],
            'This indicates a Reactiph bug (a Parser output type the Compiler doesn\'t handle) — please report it.',
        );
    }
}
