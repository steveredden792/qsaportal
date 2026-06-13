<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Provider extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name'];

    public function report(): HasOne
    {
        return $this->hasOne(Report::class);
    }

    public function charities(): BelongsToMany
    {
        return $this->belongsToMany(Charity::class, 'provider_charity_links');
    }
}
