<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

/**
 * A mobile-app end user (FreeFCC Android app member).
 *
 * Separate from the Filament admin `User` model on purpose: members never
 * log into the admin panel, and admins never authenticate via the API.
 */
class Member extends Model
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'name',
        'username',
        'password',
        'is_active',
        'expires_at',
        'device_id',
        'device_registered_at',
        'last_login_at',
        'last_login_ip',
        'app_version',
        'notes',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'device_registered_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function loginLogs(): HasMany
    {
        return $this->hasMany(LoginLog::class);
    }

    /** True once expires_at has passed. Members with no expiry never expire. */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** True when the account is enabled and its subscription (if any) hasn't expired. */
    public function isUsable(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }

    /** Clears the device lock so the member can log in from a new device. */
    public function resetDevice(): void
    {
        $this->forceFill([
            'device_id' => null,
            'device_registered_at' => null,
        ])->save();

        $this->tokens()->delete();
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->is_active) {
                return 'Pasif';
            }
            if ($this->isExpired()) {
                return 'Süresi Doldu';
            }

            return 'Aktif';
        });
    }
}
