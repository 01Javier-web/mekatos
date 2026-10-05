<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use App\UserRole;
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'role' => UserRole::class, 
            'failed_login_attempts' => 'integer',
            'failed_login_window_started_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    /**
     * Bloqueado por intentos fallidos de inicio de sesión (independiente de is_active).
     */
    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function handledOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'handled_by_user_id');
    }

    public function deliveredOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'delivered_by_user_id');
    }

    public function orderStatusHistories(): HasMany
    {
        return $this->hasMany(
            OrderStatusHistory::class,
            'changed_by_user_id'
        );
    }
}