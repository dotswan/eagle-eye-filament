<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Support\Explorer;

final readonly class ParsedSearch
{
    /**
     * @param array{channels: list<string>, severities: list<string>, actions: list<string>, actorType: ?string, actorId: ?string, subjectType: ?string, subjectId: ?string, trace: ?string} $criteria
     * @param list<string> $errors
     * @param list<string> $warnings
     */
    public function __construct(
        public array $criteria,
        public string $freeText,
        public array $errors,
        public array $warnings = [],
    ) {
    }

    public function hasErrors(): bool
    {
        return [] !== $this->errors;
    }

    public function hasWarnings(): bool
    {
        return [] !== $this->warnings;
    }
}
