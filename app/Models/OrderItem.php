<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'order_round_id',
        'product_id',
        'quantity',
        'unit_price',
        'total',
        'notes',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(OrderRound::class, 'order_round_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}