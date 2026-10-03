<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $casts = [
        'is_available' => 'boolean',
        'is_portion' => 'boolean',
    ];

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price',
        'image_path',
        'is_available',
        'is_portion',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function beverageOptions(): HasMany
    {
        return $this->hasMany(BeverageOption::class)->orderBy('sort_order');
    }
}
