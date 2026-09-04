<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\TableSessionStatus;
class TableSession extends Model
{
    protected $fillable = [
        'restaurant_table_id',
        'status',
        'started_at',
        'ended_at',
    ];

    public function restaurantTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
    protected function casts (): array
    {
        return [
            'status' => TableSessionStatus::class,
        ];
    }
}