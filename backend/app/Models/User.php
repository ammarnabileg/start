<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'phone', 'password', 'avatar',
        'user_type', 'is_active', 'two_factor_enabled', 'two_factor_secret',
        'locale', 'last_login_at', 'last_login_ip',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected $casts = [
        'is_active' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function getJWTIdentifier() { return $this->getKey(); }
    public function getJWTCustomClaims(): array { return ['user_type' => $this->user_type, 'tenant_id' => $this->tenant_id]; }

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function notifications() { return $this->hasMany(Notification::class); }
    public function auditLogs() { return $this->hasMany(AuditLog::class); }

    public function isSuperAdmin(): bool { return $this->user_type === 'super_admin'; }
    public function isHR(): bool { return $this->user_type === 'hr'; }
    public function isCandidate(): bool { return $this->user_type === 'candidate'; }
}
