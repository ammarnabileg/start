<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TalentPool extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['tenant_id', 'created_by', 'name', 'name_ar', 'description', 'color'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function candidates() { return $this->belongsToMany(Candidate::class, 'talent_pool_candidates')->withPivot('notes', 'added_by')->withTimestamps(); }
}
