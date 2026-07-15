<?php

namespace Modules\Identity\Internal\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Modules\Identity\Public\Enums\Permission;
use Modules\Identity\Public\Enums\Role;

/**
 * @property string $preferred_locale Added by a module migration (not scanned by
 *                                    Larastan's migration inference), so declared here.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
        $this->unsetRelation('userRoles');
    }

    /**
     * Remove a role from this user — the counterpart to {@see self::assignRole()}
     * the acceptance mint uses to strip the Applicant role (R01) once it grants
     * Student (R02), so an admitted user no longer carries applicant rights (ADR-0013).
     * A no-op if the user does not hold the role. Any loaded `userRoles` relation is
     * dropped so a subsequent {@see self::roles()} reflects the change.
     */
    public function removeRole(Role $role): void
    {
        $this->userRoles()->where('role', $role->value)->delete();
        $this->unsetRelation('userRoles');
    }

    public function hasRole(Role $role): bool
    {
        return $this->roles()->contains($role);
    }

    /**
     * The user's effective permissions: the union across their roles, per the
     * SRS §3.2 matrix (ADR-0008). The server is the sole authority — this is
     * what the API enforces and what `/me` mirrors to the SPA.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return Permission::forRoles($this->roles()->all());
    }

    public function hasPermission(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
