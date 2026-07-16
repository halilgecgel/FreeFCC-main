<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FccSession extends Model
{
    public const FLIGHT_START_ACTIONS = ['fcc_enable', 'auto_fcc'];

    public const FLIGHT_END_ACTIONS = ['fcc_disable'];

    public const FLIGHT_ACTIONS = ['fcc_enable', 'auto_fcc', 'fcc_disable'];

    protected $fillable = [
        'member_id',
        'action',
        'success',
        'duration_seconds',
        'keepalive_count',
        'ce_reset_blocks',
        'aircraft_serial',
        'controller_model',
        'device_model',
        'latitude',
        'longitude',
        'province',
        'district',
        'neighborhood',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @var array{start: ?self, end: ?self}|null */
    protected ?array $resolvedFlightBounds = null;

    /**
     * One row per logical flight: the first successful enable/auto_fcc after
     * the previous disable (or the member's first ever enable). Mid-flight
     * re-applies are excluded so they only appear as events inside the flight.
     */
    public function scopeFlightStarts(Builder $query): Builder
    {
        $starts = self::FLIGHT_START_ACTIONS;
        $ends = self::FLIGHT_END_ACTIONS;

        return $query
            ->where('success', true)
            ->whereIn('action', $starts)
            ->whereNotExists(function ($sub) use ($starts, $ends) {
                $sub->selectRaw('1')
                    ->from('fcc_sessions as prev_start')
                    ->whereColumn('prev_start.member_id', 'fcc_sessions.member_id')
                    ->where('prev_start.success', true)
                    ->whereIn('prev_start.action', $starts)
                    ->where(function ($q) {
                        $q->whereColumn('prev_start.created_at', '<', 'fcc_sessions.created_at')
                            ->orWhere(function ($q) {
                                $q->whereColumn('prev_start.created_at', 'fcc_sessions.created_at')
                                    ->whereColumn('prev_start.id', '<', 'fcc_sessions.id');
                            });
                    })
                    ->whereNotExists(function ($mid) use ($ends) {
                        $mid->selectRaw('1')
                            ->from('fcc_sessions as mid_end')
                            ->whereColumn('mid_end.member_id', 'fcc_sessions.member_id')
                            ->where('mid_end.success', true)
                            ->whereIn('mid_end.action', $ends)
                            ->where(function ($q) {
                                $q->whereColumn('mid_end.created_at', '>', 'prev_start.created_at')
                                    ->orWhere(function ($q) {
                                        $q->whereColumn('mid_end.created_at', 'prev_start.created_at')
                                            ->whereColumn('mid_end.id', '>', 'prev_start.id');
                                    });
                            })
                            ->where(function ($q) {
                                $q->whereColumn('mid_end.created_at', '<', 'fcc_sessions.created_at')
                                    ->orWhere(function ($q) {
                                        $q->whereColumn('mid_end.created_at', 'fcc_sessions.created_at')
                                            ->whereColumn('mid_end.id', '<', 'fcc_sessions.id');
                                    });
                            });
                    });
            });
    }

    /**
     * Resolve the logical flight window that contains this session event.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function resolveFlightWindow(): array
    {
        $startEvent = $this->resolveFlightStartEvent();
        $endEvent = $this->resolveFlightEndEvent($startEvent);

        $startedAt = $startEvent?->created_at ?? $this->created_at;
        $endedAt = $endEvent?->created_at;

        if ($endedAt === null && $startEvent && $startEvent->duration_seconds) {
            $endedAt = $startedAt->copy()->addSeconds((int) $startEvent->duration_seconds);
        }

        if ($endedAt === null && in_array($this->action, self::FLIGHT_END_ACTIONS, true) && $this->duration_seconds) {
            $endedAt = $this->created_at;
            $startedAt = $this->created_at->copy()->subSeconds((int) $this->duration_seconds);
        }

        if ($endedAt === null) {
            $endedAt = $this->isOngoingFlight($startEvent) ? now() : ($this->created_at ?? now());
        }

        if ($endedAt->lt($startedAt)) {
            $endedAt = $startedAt->copy();
        }

        // Small buffer so near-simultaneous telemetry is included.
        return [
            $startedAt->copy()->subSeconds(5),
            $endedAt->copy()->addSeconds(5),
        ];
    }

    public function resolveFlightStartEvent(): ?self
    {
        return $this->flightBounds()['start'];
    }

    public function resolveFlightEndEvent(?self $startEvent = null): ?self
    {
        if ($startEvent !== null && $this->resolvedFlightBounds !== null) {
            $cachedStart = $this->resolvedFlightBounds['start'];

            if ($cachedStart !== null && $cachedStart->is($startEvent)) {
                return $this->resolvedFlightBounds['end'];
            }
        }

        if ($startEvent === null) {
            return $this->flightBounds()['end'];
        }

        return $this->findFlightEndEvent($startEvent);
    }

    public function isOngoingFlight(?self $startEvent = null): bool
    {
        $startEvent ??= $this->resolveFlightStartEvent();

        if ($startEvent === null) {
            return false;
        }

        return $this->resolveFlightEndEvent($startEvent) === null;
    }

    /**
     * @return 'ongoing'|'auto_closed'|'completed'
     */
    public function flightStatus(): string
    {
        if ($this->isOngoingFlight()) {
            return 'ongoing';
        }

        $end = $this->resolveFlightEndEvent();

        if ($end?->failure_reason === 'auto_closed_offline') {
            return 'auto_closed';
        }

        return 'completed';
    }

    public static function flightStatusLabel(string $status): string
    {
        return match ($status) {
            'ongoing' => 'Devam ediyor',
            'auto_closed' => 'Otomatik kapandı',
            default => 'Tamamlandı',
        };
    }

    public static function flightStatusColor(string $status): string
    {
        return match ($status) {
            'ongoing' => 'warning',
            'auto_closed' => 'gray',
            default => 'success',
        };
    }

    /**
     * @return array{start: ?self, end: ?self}
     */
    protected function flightBounds(): array
    {
        if ($this->resolvedFlightBounds !== null) {
            return $this->resolvedFlightBounds;
        }

        $start = $this->findFlightStartEvent();
        $end = $start ? $this->findFlightEndEvent($start) : null;

        return $this->resolvedFlightBounds = [
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Root enable for the flight containing this event: the first successful
     * start after the previous disable (ignores mid-flight re-applies).
     */
    protected function findFlightStartEvent(): ?self
    {
        $lastDisable = static::query()
            ->where('member_id', $this->member_id)
            ->where('success', true)
            ->whereIn('action', self::FLIGHT_END_ACTIONS)
            ->where(function (Builder $query) {
                $query->where('created_at', '<', $this->created_at)
                    ->orWhere(function (Builder $query) {
                        $query->where('created_at', $this->created_at)
                            ->where('id', '<', $this->id);
                    });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $startQuery = static::query()
            ->where('member_id', $this->member_id)
            ->where('success', true)
            ->whereIn('action', self::FLIGHT_START_ACTIONS)
            ->where(function (Builder $query) {
                $query->where('created_at', '<', $this->created_at)
                    ->orWhere(function (Builder $query) {
                        $query->where('created_at', $this->created_at)
                            ->where('id', '<=', $this->id);
                    });
            });

        if ($lastDisable !== null) {
            $startQuery->where(function (Builder $query) use ($lastDisable) {
                $query->where('created_at', '>', $lastDisable->created_at)
                    ->orWhere(function (Builder $query) use ($lastDisable) {
                        $query->where('created_at', $lastDisable->created_at)
                            ->where('id', '>', $lastDisable->id);
                    });
            });
        }

        return $startQuery
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }

    protected function findFlightEndEvent(self $startEvent): ?self
    {
        return static::query()
            ->where('member_id', $this->member_id)
            ->where('success', true)
            ->whereIn('action', self::FLIGHT_END_ACTIONS)
            ->where(function (Builder $query) use ($startEvent) {
                $query->where('created_at', '>', $startEvent->created_at)
                    ->orWhere(function (Builder $query) use ($startEvent) {
                        $query->where('created_at', $startEvent->created_at)
                            ->where('id', '>', $startEvent->id);
                    });
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }

    public function flightDurationSeconds(): ?int
    {
        [$start, $end] = $this->resolveFlightWindow();

        // Undo the ±5s buffer used for telemetry inclusion.
        $seconds = (int) $start->copy()->addSeconds(5)->diffInSeconds($end->copy()->subSeconds(5), false);

        return max(0, $seconds);
    }

    public function errorLogsDuringFlight(): Builder
    {
        [$start, $end] = $this->resolveFlightWindow();

        return ErrorLog::query()
            ->where('member_id', $this->member_id)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at');
    }

    public function featureUsageLogsDuringFlight(): Builder
    {
        [$start, $end] = $this->resolveFlightWindow();

        return FeatureUsageLog::query()
            ->where('member_id', $this->member_id)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at');
    }

    /**
     * App UI activity lines during the flight, with a wider pre-start window
     * so connect / serial-probe logs right before FCC enable are included.
     */
    public function appActivityLogsDuringFlight(): Builder
    {
        [$start, $end] = $this->resolveFlightWindow();

        return AppActivityLog::query()
            ->where('member_id', $this->member_id)
            ->whereBetween('created_at', [
                $start->copy()->subMinutes(2),
                $end->copy()->addSeconds(25),
            ])
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function eventsDuringFlight(): Builder
    {
        [$start, $end] = $this->resolveFlightWindow();

        return static::query()
            ->where('member_id', $this->member_id)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function locationLabel(): string
    {
        $parts = array_filter([
            $this->neighborhood,
            $this->district,
            $this->province,
        ]);

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        if ($this->latitude !== null && $this->longitude !== null) {
            return sprintf('%.5f, %.5f', $this->latitude, $this->longitude);
        }

        return '—';
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'fcc_enable' => 'FCC Etkinleştir',
            'fcc_disable' => 'FCC Durdur',
            'keepalive_start' => 'Keepalive Başlat',
            'keepalive_stop' => 'Keepalive Durdur',
            'auto_fcc' => 'Otomatik FCC',
            default => $action,
        };
    }

    public static function formatDuration(?int $seconds): string
    {
        if (! $seconds) {
            return '—';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return "{$hours}s {$minutes}dk";
        }

        if ($minutes > 0) {
            return "{$minutes}dk {$secs}sn";
        }

        return "{$secs}sn";
    }
}
