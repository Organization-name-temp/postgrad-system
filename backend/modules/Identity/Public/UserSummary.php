<?php

namespace Modules\Identity\Public;

/**
 * A read-only view of a User for consumers outside Identity (ADR-0012). This is the
 * only shape in which a User crosses the module seam: the Eloquent User model never
 * leaves `Identity\Internal`, so a consumer gets exactly these display fields —
 * id, name, email — and none of Identity's write surface, roles table, or auth
 * internals.
 *
 * Admissions is the first consumer (ticket 09): it lists the faculty Users eligible
 * to interview through {@see Directory::usersByRole()} to build Student Affairs'
 * slot-interviewer picker, and resolves an assigned interviewer's name for display
 * through {@see Directory::findMany()} — mirroring how it reads Programs through the
 * Catalog\Public ProgramSummary facade. An immutable value object.
 */
final readonly class UserSummary
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}

    /**
     * The public array shape for JSON responses — the single home for how a User
     * summary is exposed, so a picker list and an embedded summary render it
     * identically.
     *
     * @return array{id: int, name: string, email: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
