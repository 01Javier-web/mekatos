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
        'sent_at',
        'paired_order_item_id',
        'voided_at',
        'voided_by_user_id',
        'voided_round_id',
        'void_sent_at',
    ];

    protected $casts = [
        'voided_at' => 'datetime',
    ];

    /** ¿Anulada por una edición del pedido? (no cuenta en totales, cuenta ni ventas). */
    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /** Edición (ronda) en la que se anuló la línea. */
    public function voidedRound(): BelongsTo
    {
        return $this->belongsTo(OrderRound::class, 'voided_round_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(OrderRound::class, 'order_round_id');
    }

    public function pairedOrderItem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'paired_order_item_id');
    }

    public function pairedPortions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'paired_order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Salsas de este producto (EN_PRODUCTO o APARTE). */
    public function sauces(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderSauce::class)->orderBy('id');
    }
}