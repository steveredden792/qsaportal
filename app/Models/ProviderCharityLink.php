<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderCharityLink extends Model
{
    use HasFactory;

    protected $fillable = ['provider_id', 'charity_id'];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function charity(): BelongsTo
    {
        return $this->belongsTo(Charity::class);
    }
}
