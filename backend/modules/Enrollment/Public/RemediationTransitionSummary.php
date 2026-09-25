<?php

namespace Modules\Enrollment\Public;

final readonly class RemediationTransitionSummary
{
    /** @param array<string, mixed> $evidence */
    public function __construct(
        public int $id,
        public ?string $fromState,
        public string $toState,
        public array $evidence,
        public string $createdAt,
    ) {}
}
