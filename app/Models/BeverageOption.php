<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeverageOption extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'sort_order',
        'is_available',
    ];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
