<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Charity extends Model
{
    use HasFactory;

    protected $fillable = ['cc_ref', 'name', 'latest_q_score', 'latest_stability'];

    protected function casts(): array
    {
        return [
            'latest_q_score' => 'decimal:2',
            'latest_stability' => 'decimal:2',
        ];
    }

    public function report(): HasOne
    {
        return $this->hasOne(Report::class);
    }
}
