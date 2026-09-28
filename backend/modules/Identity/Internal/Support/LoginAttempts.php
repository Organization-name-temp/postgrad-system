<?php

namespace Modules\Identity\Internal\Support;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use LogicException;

/** Short-lived failure budgets; successful logins never replenish the IP budget. */
final class LoginAttempts
{
    /**
     * Hold both shared budget locks through credential verification and its outcome.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function serialized(string $email, ?string $ip, callable $callback): mixed
    {
        $store = Cache::store(config('cache.limiter', config('cache.default')))->getStore();
        if (! $store instanceof LockProvider) {
            throw new LogicException('Login attempt budgets require a cache store with atomic locks.');
        }
        $keys = array_keys($this->limits($email, $ip));
        sort($keys);
        $locks = [];
        try {
            foreach ($keys as $key) {
                $lock = $store->lock($key.':verification', 30);
                if (! $lock->get()) {
                    throw new HttpResponseException(response()->json([
                        'message' => __('auth.throttle', ['seconds' => 1]),
                    ], 429, ['Retry-After' => '1']));
                }
                $locks[] = $lock;
            }
            $this->ensureAllowed($email, $ip);

            return $callback();
        } finally {
            foreach (array_reverse($locks) as $lock) {
                $lock->release();
            }
        }
    }

    public function ensureAllowed(string $email, ?string $ip): void
    {
        $retryAfter = 0;
        foreach ($this->limits($email, $ip) as $key => $maximum) {
            if (RateLimiter::tooManyAttempts($key, $maximum)) {
                $retryAfter = max($retryAfter, RateLimiter::availableIn($key));
            }
        }
        if ($retryAfter > 0) {
            throw new HttpResponseException(response()->json([
                'message' => __('auth.throttle', ['seconds' => $retryAfter]),
            ], 429, ['Retry-After' => (string) $retryAfter]));
        }
    }

    public function failed(string $email, ?string $ip): void
    {
        foreach (array_keys($this->limits($email, $ip)) as $key) {
            RateLimiter::hit($key, (int) config('auth.login_throttle.decay_seconds'));
        }
    }

    public function succeeded(string $email): void
    {
        RateLimiter::clear($this->accountKey($email));
    }

    /** @return array<string, int> */
    private function limits(string $email, ?string $ip): array
    {
        return [
            $this->accountKey($email) => (int) config('auth.login_throttle.account_max_attempts'),
            'login-ip:'.hash('sha256', $ip ?? 'unknown') => (int) config('auth.login_throttle.ip_max_attempts'),
        ];
    }

    private function accountKey(string $email): string
    {
        return 'login-account:'.hash('sha256', mb_strtolower(trim($email)));
    }
}
