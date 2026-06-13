<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Market extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name'];

    public function report(): HasOne
    {
        return $this->hasOne(Report::class);
    }
}
