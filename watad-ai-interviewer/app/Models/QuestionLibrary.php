<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionLibrary extends Model
{
    protected $guarded = ['id'];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'library_id');
    }
}
