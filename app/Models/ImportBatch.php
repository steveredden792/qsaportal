<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'label', 'type', 'status', 'rows',
        'charities_created', 'charities_updated', 'issues_created',
    ];

    protected $attributes = [
        'status' => 'pending',
        'rows' => 0,
        'charities_created' => 0,
        'charities_updated' => 0,
        'issues_created' => 0,
    ];

    protected function casts(): array
    {
        return [
            'rows' => 'integer',
            'charities_created' => 'integer',
            'charities_updated' => 'integer',
            'issues_created' => 'integer',
        ];
    }
}
