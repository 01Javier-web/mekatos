<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JuiceFruit extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
        ];
    }
}
