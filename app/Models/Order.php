<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'total_pence', 'currency', 'status',
        'stripe_checkout_session_id', 'stripe_payment_intent_id',
    ];

    protected $attributes = [
        'currency' => 'gbp',
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return ['total_pence' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
