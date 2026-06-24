<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    protected $fillable = [
        'tenant_id', 'application_id', 'created_by', 'title', 'salary', 'currency',
        'employment_type', 'start_date', 'benefits', 'notes', 'status', 'pdf_path',
        'sent_at', 'responded_at', 'candidate_response', 'candidate_notes', 'expires_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function application() { return $this->belongsTo(Application::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function tenant() { return $this->belongsTo(Tenant::class); }
}
