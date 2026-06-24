<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['tenant_id', 'name', 'name_ar', 'manager_id'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function manager() { return $this->belongsTo(User::class, 'manager_id'); }
    public function jobs() { return $this->hasMany(RecruitmentJob::class); }
}
