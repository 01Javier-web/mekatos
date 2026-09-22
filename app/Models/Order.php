<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\OrderStatus;
use App\Enums\OrderType;

class Order extends Model
{
    protected $fillable = [
        'table_session_id',
        'type',
        'status',
        'subtotal',
        'packaging_fee',
        'delivery_fee',
        'tax',
        'total',
        'customer_name',
        'customer_phone',
        'delivery_address',
        'delivery_reference',
        'notes',
        'handled_by_user_id',
        'delivered_by_user_id',
        'delivered_at',
        'paid_at',
        'paid_by_user_id',
    ];

    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(OrderRound::class)->orderBy('number');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by_user_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'delivered_at' => 'datetime',
            'paid_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'packaging_fee' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }
}
