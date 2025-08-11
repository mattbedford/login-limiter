<?php declare(strict_types=1);

namespace LAL\Security;

final class VerificationResult
{
    public function __construct(
        public bool $ok,
        /** @var string[] */
        public array $errors = [],
        public ?string $hostname = null,
        public ?string $action   = null,
        public ?string $cdata    = null
    ) {}

    public function errorSummary(): string
    {
        return $this->ok ? '' : implode(', ', $this->errors);
    }
}
