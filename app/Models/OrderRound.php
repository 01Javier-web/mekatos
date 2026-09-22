<?php

namespace AppModels;

use IlluminateDatabaseEloquentModel;
use IlluminateDatabaseEloquentRelationsBelongsTo;
use IlluminateDatabaseEloquentRelationsHasMany;

class OrderRound extends Model
{
    protected $fillable = [
        'order_id',
        'number',
        'created_by_user_id',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
