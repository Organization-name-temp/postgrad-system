<?php

namespace Modules\Records\Internal\Support;

use Illuminate\Support\Facades\DB;

final class TranscriptFee
{
    public static function format(int $minor): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function minor(string $fee): int
    {
        $parts = explode('.', $fee);

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    /** @return array{available: bool, fee: string|null, currency: string, instructions_en?: string, instructions_ar?: string} */
    public function read(bool $instructions = false): array
    {
        $fee = DB::table('transcript_fee')->where('id', 1)->first();
        $data = ['available' => $fee !== null, 'fee' => $fee === null ? null : self::format((int) $fee->fee_minor), 'currency' => 'EGP'];
        if ($instructions && $fee !== null) {
            $data['instructions_en'] = (string) $fee->instructions_en;
            $data['instructions_ar'] = (string) $fee->instructions_ar;
        }

        return $data;
    }
}
