<?php

namespace App\Models;

use App\Enums\AssetType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = ['issue_id', 'type', 'disk', 'path', 'original_filename', 'size', 'mime'];

    protected function casts(): array
    {
        return ['type' => AssetType::class];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
