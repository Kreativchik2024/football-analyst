<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ========== Методы доступа ==========

    public function canAccessAiPredictions(): bool
    {
        return in_array($this->role, ['super_admin', 'admin', 'user_plus']);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    // ========== Отношения ==========

    public function predictions()
    {
        return $this->hasMany(UserPrediction::class);
    }

    public function balance()
    {
        return $this->hasOne(UserBalance::class)->withDefault(['balance' => 100000]);
    }

    /**
     * Получить баланс через отношение (без N+1)
     */
    public function getBalanceResult()
    {
        return $this->balance()->first()?->balance ?? 100000;
    }
}