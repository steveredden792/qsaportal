<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'label', 'type', 'folder', 'status', 'errors', 'rows',
        'charities_created', 'charities_updated',
        'providers_created', 'providers_updated', 'issues_created',
    ];

    protected $attributes = [
        'status' => 'draft',
        'rows' => 0,
        'charities_created' => 0,
        'charities_updated' => 0,
        'providers_created' => 0,
        'providers_updated' => 0,
        'issues_created' => 0,
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'rows' => 'integer',
            'charities_created' => 'integer',
            'charities_updated' => 'integer',
            'providers_created' => 'integer',
            'providers_updated' => 'integer',
            'issues_created' => 'integer',
        ];
    }
}
