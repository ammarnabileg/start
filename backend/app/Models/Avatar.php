<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Avatar extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'name_ar', 'heygen_avatar_id', 'heygen_voice_id',
        'gender', 'personality', 'language', 'photo', 'intro_message',
        'intro_message_ar', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function jobs() { return $this->hasMany(RecruitmentJob::class); }
}
