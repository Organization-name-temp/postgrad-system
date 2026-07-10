<?php

namespace Modules\Identity\Internal\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Modules\Identity\Public\Enums\Role;

/**
 * @property string $preferred_locale Added by a module migration (not scanned by
 *                                    Larastan's migration inference), so declared here.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'preferred_locale',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /** @return HasMany<UserRole, $this> */
    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    /**
     * The Role enums this user holds. Effective permissions (ticket 05) are the
     * union of the permissions each of these roles grants.
     *
     * @return Collection<int, Role>
     */
    public function roles(): Collection
    {
        return $this->userRoles->map(fn (UserRole $ur) => Role::from((string) $ur->role))->values();
    }

    public function assignRole(Role $role): void
    {
        $this->userRoles()->firstOrCreate(['role' => $role->value]);
    }

    public function hasRole(Role $role): bool
    {
        return $this->roles()->contains($role);
    }
}
