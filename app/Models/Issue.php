<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    use HasFactory;

    protected $fillable = ['report_id', 'version_label', 'published_at', 'is_current', 'q_score', 'stability', 'q_grade', 'stability_grade'];

    protected function casts(): array
    {
        return [
            'published_at' => 'date',
            'is_current' => 'boolean',
            'q_score' => 'decimal:2',
            'stability' => 'decimal:2',
            'stability_grade' => 'decimal:1',
        ];
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function referencedCharities(): BelongsToMany
    {
        return $this->belongsToMany(Charity::class, 'far_pir_references');
    }
}
