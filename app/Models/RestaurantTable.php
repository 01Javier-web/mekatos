<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\TableStatus;

class RestaurantTable extends Model
{
    protected $fillable = [
        'number',
        'name',
        'capacity',
        'qr_token',
        'status',
    ];

    public function tableSessions(): HasMany
    {
        return $this->hasMany(TableSession::class);
    }

    protected function casts(): array
    {
        return [
            'status' => TableStatus::class,
        ];
    }
}