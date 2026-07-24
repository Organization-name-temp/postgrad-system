<?php

namespace Modules\Enrollment\Internal\Support;

/**
 * The pure attendance arithmetic (ADR-0019, ticket 08): the derived percentage and the
 * downward-crossing test that gates the audit-at-risk notification. No stored state — the
 * percentage is always recomputed from the marks, and "at risk" is decided by comparing the
 * percentage before and after a register save, so the notification fires once per downward
 * crossing and never on every mark. Kept as a small pure class (the CreditHours / MeetingTime
 * precedent) so both answers are unit-tested directly.
 */
final class AttendanceCalculator
{
    /**
     * A Student's attendance percentage for one Enrollment: present marks ÷ recorded marks
     * on non-cancelled sessions, as a 0–100 float. `null` when nothing has been recorded yet
     * (no division by zero, and "no data" is distinguishable from a genuine 0%).
     */
    public static function percentage(int $present, int $recorded): ?float
    {
        if ($recorded <= 0) {
            return null;
        }

        return $present / $recorded * 100;
    }

    /**
     * The wire shape of one Enrollment's attendance — the single source of truth for that
     * shape, so the register roster, My Schedule, and the "no data" fallback can never drift.
     * The display percentage is rounded (0 with `recorded: 0` meaning "no data yet", never
     * null, so the typed client stays non-nullable); `at_risk` is judged on the *raw*
     * percentage (a 74.5% is at risk even though it rounds to 75) and is only ever true for an
     * Audit enrollment below the threshold.
     *
     * @return array{present: int, recorded: int, percentage: int, at_risk: bool}
     */
    public static function block(int $present, int $recorded, bool $isAudit, float $threshold): array
    {
        $raw = self::percentage($present, $recorded);

        return [
            'present' => $present,
            'recorded' => $recorded,
            'percentage' => $raw === null ? 0 : (int) round($raw),
            'at_risk' => $isAudit && $raw !== null && $raw < $threshold,
        ];
    }

    /**
     * The "nothing recorded yet" attendance block — the same shape as {@see self::block()},
     * for a surface that carries no derived attendance (a just-added Enrollment). Built from
     * `block()` so there is one shape, not two literals.
     *
     * @return array{present: int, recorded: int, percentage: int, at_risk: bool}
     */
    public static function emptyBlock(): array
    {
        return self::block(0, 0, false, 0.0);
    }

    /**
     * Whether a register save took the percentage on a **downward crossing** below the
     * threshold: it is below now and was not below before (a null "before" — no prior data —
     * counts as not-below, so the first recorded absence that lands below the threshold
     * crosses). Exactly at the threshold is not below. Recovering above and dipping again is a
     * fresh crossing. This is the single trigger for the `attendance.audit_at_risk`
     * notification.
     */
    public static function crossedBelow(?float $before, ?float $after, float $threshold): bool
    {
        $wasBelow = $before !== null && $before < $threshold;
        $isBelow = $after !== null && $after < $threshold;

        return $isBelow && ! $wasBelow;
    }
}
