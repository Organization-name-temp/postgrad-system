<?php

namespace Modules\Identity\Internal\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single role assignment (one row per user↔role). The role set is fixed in
 * the Role enum; only these assignment rows are editable at runtime (ADR-0008).
 *
 * @property int $user_id
 * @property string $role
 */
class UserRole extends Model
{
    protected $table = 'user_roles';

    /** @var list<string> */
    protected $fillable = ['user_id', 'role'];
}
