<?php

namespace Modules\Identity\Internal\Registration;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Contracts\EmailVerifier;

/**
 * Eloquent {@see EmailVerifier}: the only place outside Identity that a User's
 * `email_verified_at` is read or set, keeping that Identity-owned column behind
 * the Public seam. Uses the framework's own {@see MustVerifyEmail}
 * helpers on the User model so the semantics match Laravel's built-in verification.
 */
class EloquentEmailVerifier implements EmailVerifier
{
    public function isVerified(int $userId): bool
    {
        return User::query()->whereKey($userId)->whereNotNull('email_verified_at')->exists();
    }

    public function markVerified(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        // Idempotent: only stamp (and persist) when not already verified, so a
        // re-clicked link neither errors nor moves the timestamp.
        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }
    }
}
