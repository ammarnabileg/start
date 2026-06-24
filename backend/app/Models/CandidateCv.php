<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateCv extends Model
{
    protected $fillable = [
        'candidate_id', 'file_path', 'file_name', 'file_type',
        'file_size', 'is_primary', 'parsed_data', 'parsing_confidence', 'parsed_at',
    ];

    protected $casts = [
        'parsed_data' => 'array',
        'is_primary' => 'boolean',
        'parsed_at' => 'datetime',
    ];

    public function candidate() { return $this->belongsTo(Candidate::class); }
    public function applications() { return $this->hasMany(Application::class); }
}
