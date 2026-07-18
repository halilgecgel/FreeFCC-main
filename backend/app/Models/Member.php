<?php

namespace App\Models;

use App\Services\FlightAutoCloseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /** Minutes without a heartbeat before a member is treated as offline. */
    public const HEARTBEAT_TIMEOUT_MINUTES = 2;

    protected $fillable = [
        'name',
        'username',
        'phone',
        'password',
        'is_active',
        'expires_at',
        'device_id',
        'device_registered_at',
        'device_model_id',
        'last_login_at',
        'last_login_ip',
        'app_version',
        'is_online',
        'last_heartbeat_at',
        'total_online_seconds',
        'notes',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_online' => 'boolean',
            'expires_at' => 'datetime',
            'device_registered_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function deviceModel(): BelongsTo
    {
        return $this->belongsTo(DeviceModel::class);
    }

    public function loginLogs(): HasMany
    {
        return $this->hasMany(LoginLog::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(MemberActivityLog::class);
    }

    public function deviceTelemetry(): HasMany
    {
        return $this->hasMany(DeviceTelemetry::class);
    }

    public function fccSessions(): HasMany
    {
        return $this->hasMany(FccSession::class);
    }

    public function featureUsageLogs(): HasMany
    {
        return $this->hasMany(FeatureUsageLog::class);
    }

    public function errorLogs(): HasMany
    {
        return $this->hasMany(ErrorLog::class);
    }

    public function appActivityLogs(): HasMany
    {
        return $this->hasMany(AppActivityLog::class);
    }

    public function connectionMetrics(): HasMany
    {
        return $this->hasMany(ConnectionMetric::class);
    }

    public function notificationReceipts(): HasMany
    {
        return $this->hasMany(AppNotificationReceipt::class);
    }

    public function markOnline(string $ip = null): void
    {
        $now = now();

        if (! $this->is_online) {
            MemberActivityLog::create([
                'member_id' => $this->id,
                'event' => 'online',
                'started_at' => $now,
                'ip_address' => $ip,
            ]);
        }

        $this->forceFill([
            'is_online' => true,
            'last_heartbeat_at' => $now,
        ])->save();
    }

    public function markOffline(): void
    {
        if ($this->is_online) {
            $lastLog = $this->activityLogs()
                ->where('event', 'online')
                ->whereNull('ended_at')
                ->latest()
                ->first();

            $duration = 0;
            if ($lastLog) {
                $duration = (int) $lastLog->started_at->diffInSeconds(now());
                $lastLog->update([
                    'ended_at' => now(),
                    'duration_seconds' => $duration,
                ]);
            }

            $this->forceFill([
                'is_online' => false,
                'total_online_seconds' => $this->total_online_seconds + $duration,
            ])->save();
        }

        // Always attempt close: is_online may already be false while a flight is still open
        // (e.g. flag flipped earlier, or auto-close added after the member went offline).
        app(FlightAutoCloseService::class)->closeOpenFlightIfNeeded($this);
    }

    /** True when the online flag is set and the last heartbeat is still fresh. */
    public function isCurrentlyOnline(): bool
    {
        return $this->is_online
            && $this->last_heartbeat_at !== null
            && $this->last_heartbeat_at->gte(now()->subMinutes(self::HEARTBEAT_TIMEOUT_MINUTES));
    }

    /** Members whose heartbeat has not timed out. */
    public function scopeCurrentlyOnline(Builder $query): Builder
    {
        return $query
            ->where('is_online', true)
            ->where('last_heartbeat_at', '>=', now()->subMinutes(self::HEARTBEAT_TIMEOUT_MINUTES));
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
        $this->markOffline();

        $this->forceFill([
            'device_id' => null,
            'device_registered_at' => null,
        ])->save();

        $this->tokens()->delete();
    }

    /** Clears the selected device model so the member can pick again on next launch. */
    public function resetDeviceModel(): void
    {
        $this->forceFill([
            'device_model_id' => null,
        ])->save();
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
