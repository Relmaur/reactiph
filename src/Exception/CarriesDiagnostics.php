<?php

declare(strict_types=1);

namespace Reactiph\Exception;

/**
 * Storage and accessors backing {@see ReactiphException}. A concrete
 * exception class uses this trait, extends the most semantically-correct
 * SPL exception (`RuntimeException`, `InvalidArgumentException`,
 * `LogicException`, ...), and exposes one named constructor per distinct
 * failure case (e.g. `ParseException::missingClosingTag(...)`) that fills
 * in the code/hint/context via {@see self::withDiagnostics()} — callers
 * should never build one with `new` and a raw message.
 */
trait CarriesDiagnostics
{
    abstract public function getMessage(): string;

    private string $diagnosticCode = '';
    private ?string $diagnosticHint = null;

    /** @var array<string, mixed> */
    private array $diagnosticContext = [];

    public function code(): string
    {
        return $this->diagnosticCode;
    }

    public function hint(): ?string
    {
        return $this->diagnosticHint;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->diagnosticContext;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function withDiagnostics(string $code, array $context = [], ?string $hint = null): static
    {
        $this->diagnosticCode = $code;
        $this->diagnosticContext = $context;
        $this->diagnosticHint = $hint;

        return $this;
    }

    /**
     * @return array{code: string, message: string, hint: ?string, context: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        return [
            'code' => $this->diagnosticCode,
            'message' => $this->getMessage(),
            'hint' => $this->diagnosticHint,
            'context' => $this->diagnosticContext,
        ];
    }
}
