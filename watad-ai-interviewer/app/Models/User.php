<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleSlug;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at'         => 'datetime',
        'last_login_at'             => 'datetime',
        'is_active'                 => 'boolean',
        'password'                  => 'hashed',
        'two_factor_secret'         => 'encrypted',
        'two_factor_recovery_codes' => 'encrypted',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function managedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'manager_id');
    }

    public function hasRole(RoleSlug|string $role): bool
    {
        $slug = $role instanceof RoleSlug ? $role->value : $role;
        return $this->roles->contains('slug', $slug);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole(RoleSlug::SuperAdmin)) {
            return true;
        }
        return $this->roles
            ->flatMap(fn (Role $r) => $r->permissions)
            ->contains('slug', $permission);
    }

    /** Department ids this user is scoped to (empty = all). */
    public function scopedDepartmentIds(): array
    {
        foreach ($this->roles as $role) {
            if (RoleSlug::from($role->slug)->isDepartmentScoped()) {
                return $this->managedDepartments->pluck('id')->all();
            }
        }
        return [];
    }
}
