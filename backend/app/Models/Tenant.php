<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'domain', 'logo', 'industry', 'country',
        'timezone', 'default_language', 'status', 'openai_api_key',
        'heygen_api_key', 'career_page_title', 'career_page_description',
        'career_page_logo', 'primary_color', 'settings',
    ];

    protected $casts = ['settings' => 'array'];

    protected $hidden = ['openai_api_key', 'heygen_api_key'];

    public function users() { return $this->hasMany(User::class); }
    public function jobs() { return $this->hasMany(RecruitmentJob::class); }
    public function candidates() { return $this->hasMany(Application::class); }
    public function departments() { return $this->hasMany(Department::class); }
    public function avatars() { return $this->hasMany(Avatar::class); }
    public function talentPools() { return $this->hasMany(TalentPool::class); }
    public function aiUsageLogs() { return $this->hasMany(AiUsageLog::class); }

    public function getEffectiveOpenaiKey(): ?string
    {
        return $this->openai_api_key ?: SystemSetting::get('openai_api_key');
    }

    public function getEffectiveHeygenKey(): ?string
    {
        return $this->heygen_api_key ?: SystemSetting::get('heygen_api_key');
    }
}
