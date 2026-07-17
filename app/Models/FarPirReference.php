<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarPirReference extends Model
{
    use HasFactory;

    protected $fillable = ['issue_id', 'charity_id'];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function charity(): BelongsTo
    {
        return $this->belongsTo(Charity::class);
    }
}
