<?php

namespace App\Services;

use App\Models\DeviceTelemetry;
use App\Models\FccSession;
use App\Models\Member;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends flight start/end notices to WhatsApp (group by name, or temporary phone override).
 */
class FlightGroupNotificationService
{
    public function __construct(
        protected EvolutionApiService $evolution,
    ) {}

    public function notifySession(Member $member, FccSession $session, array $location = []): void
    {
        if (! $session->success) {
            return;
        }

        if (! in_array($session->action, ['fcc_enable', 'auto_fcc', 'fcc_disable'], true)) {
            return;
        }

        if (! $this->evolution->isConfigured()) {
            Log::warning('FlightGroupNotification: Evolution API yapılandırılmamış');

            return;
        }

        $isStart = in_array($session->action, ['fcc_enable', 'auto_fcc'], true);

        if ($isStart && $this->isReapplyWhileActive($member, $session)) {
            Log::info('FlightGroupNotification: yeniden uygulama — başlangıç mesajı atlandı', [
                'member_id' => $member->id,
                'session_id' => $session->id,
            ]);

            return;
        }

        $recipient = $this->resolveRecipient();
        if ($recipient === null) {
            Log::warning('FlightGroupNotification: WhatsApp alıcısı bulunamadı');

            return;
        }

        $message = $isStart
            ? $this->buildStartMessage($member, $session, $location)
            : $this->buildEndMessage($member, $session, $location);

        try {
            $this->evolution->sendText($recipient, $message);
            Log::info('FlightGroupNotification: mesaj gönderildi', [
                'member_id' => $member->id,
                'action' => $session->action,
                'recipient' => $recipient,
            ]);
        } catch (\Throwable $e) {
            Log::error('FlightGroupNotification: mesaj gönderilemedi', [
                'member_id' => $member->id,
                'action' => $session->action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Prefer temporary phone override; otherwise resolve the configured group.
     */
    public function resolveRecipient(): ?string
    {
        $override = trim((string) config('services.evolution.flight_notify_to', ''));
        if ($override !== '') {
            return $override;
        }

        return $this->resolveGroupJid();
    }

    /**
     * True when FCC is re-applied while an earlier enable/auto_fcc is still the last flight event
     * and that enable is within the reapply cooldown (avoids spam; stale opens allow a new start message).
     */
    protected function isReapplyWhileActive(Member $member, FccSession $session): bool
    {
        $previous = FccSession::query()
            ->where('member_id', $member->id)
            ->where('id', '<', $session->id)
            ->where('success', true)
            ->whereIn('action', ['fcc_enable', 'auto_fcc', 'fcc_disable'])
            ->latest('id')
            ->first();

        if ($previous === null || ! in_array($previous->action, ['fcc_enable', 'auto_fcc'], true)) {
            return false;
        }

        $cooldownMinutes = max(0, (int) config('services.evolution.flight_reapply_cooldown_minutes', 15));

        if ($cooldownMinutes === 0 || $previous->created_at === null) {
            return true;
        }

        return $previous->created_at->gt(now()->subMinutes($cooldownMinutes));
    }

    public function resolveGroupJid(): ?string
    {
        $groupName = trim((string) config(
            'services.evolution.flight_group_name',
            'ŞANLIURFA DRONE PİLOTLARI'
        ));

        if ($groupName === '') {
            return null;
        }

        $cacheKey = 'evolution.flight_group_jid.'.md5(
            $this->evolution->instanceName().'|'.$groupName
        );

        $jid = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($groupName) {
            return $this->findGroupJidByName($groupName);
        });

        if ($jid === null) {
            Cache::forget($cacheKey);
        }

        return $jid;
    }

    protected function findGroupJidByName(string $groupName): ?string
    {
        try {
            $groups = $this->evolution->fetchAllGroups();
        } catch (\Throwable $e) {
            Log::warning('FlightGroupNotification: grup listesi alınamadı', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $needle = mb_strtolower(trim($groupName));
        $exact = null;
        $partial = null;

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $subject = mb_strtolower(trim((string) ($group['subject'] ?? $group['name'] ?? '')));
            $id = trim((string) ($group['id'] ?? $group['jid'] ?? $group['groupJid'] ?? ''));

            if ($id === '' || $subject === '') {
                continue;
            }

            if ($subject === $needle) {
                $exact = $id;
                break;
            }

            if ($partial === null && str_contains($subject, $needle)) {
                $partial = $id;
            }
        }

        $resolved = $exact ?? $partial;

        if ($resolved !== null) {
            Log::info('FlightGroupNotification: grup ID instance üzerinden çözüldü', [
                'group_name' => $groupName,
                'group_jid' => $resolved,
            ]);
        }

        return $resolved;
    }

    /**
     * @param  array{latitude?: float|null, longitude?: float|null, province?: string|null, district?: string|null, neighborhood?: string|null, device_model?: string|null}  $location
     */
    protected function buildStartMessage(Member $member, FccSession $session, array $location): string
    {
        $pilot = $this->pilotLabel($member);
        $device = $this->deviceLabel($member, $session, $location);
        $place = $this->locationLabel($member, $location);
        $time = now()->timezone('Europe/Istanbul')->format('d.m.Y H:i');

        return implode("\n", [
            '🛫 *Uçuş başladı*',
            '',
            "👤 Pilot: {$pilot}",
            "📱 Cihaz: {$device}",
            "📍 Konum: {$place}",
            "🕐 Saat: {$time}",
            '',
            '_ŞANLIURFA DRONE PİLOTLARI_',
        ]);
    }

    /**
     * @param  array{latitude?: float|null, longitude?: float|null, province?: string|null, district?: string|null, neighborhood?: string|null, device_model?: string|null}  $location
     */
    protected function buildEndMessage(Member $member, FccSession $session, array $location): string
    {
        $pilot = $this->pilotLabel($member);
        $device = $this->deviceLabel($member, $session, $location);
        $place = $this->locationLabel($member, $location);
        $duration = $this->formatDuration((int) ($session->duration_seconds ?? 0));
        $time = now()->timezone('Europe/Istanbul')->format('d.m.Y H:i');

        $lines = [
            '🛬 *Uçuş tamamlandı*',
            '',
            "👤 Pilot: {$pilot}",
            "📱 Cihaz: {$device}",
            "📍 Konum: {$place}",
            "⏱ Süre: {$duration}",
            "🕐 Saat: {$time}",
        ];

        if ($session->failure_reason === FlightAutoCloseService::REASON_AUTO_CLOSED_OFFLINE) {
            $lines[] = '';
            $lines[] = '_Bağlantı kesildi (kumanda kapandı veya uygulama yanıt vermedi)_';
        }

        $lines[] = '';
        $lines[] = '_ŞANLIURFA DRONE PİLOTLARI_';

        return implode("\n", $lines);
    }

    protected function pilotLabel(Member $member): string
    {
        $parts = array_filter([
            $member->name,
            $member->username ? "({$member->username})" : null,
        ]);

        return $parts !== [] ? implode(' ', $parts) : ('#'.$member->id);
    }

    /**
     * Bağlı drone etiketi — RC kumanda modeli (Build.DEVICE / DJI RC 2) kullanılmaz.
     *
     * @param  array{device_model?: string|null}  $location
     */
    protected function deviceLabel(Member $member, FccSession $session, array $location): string
    {
        $serial = $this->resolveAircraftSerial($member, $session);
        $droneModel = $this->resolveDroneModel($member, $session, $location, $serial);

        $parts = array_filter([
            $droneModel,
            $serial ? 'SN: '.$serial : null,
        ]);

        return $parts !== [] ? implode(' · ', $parts) : 'Bilinmiyor';
    }

    protected function resolveAircraftSerial(Member $member, FccSession $session): ?string
    {
        if (filled($session->aircraft_serial)) {
            return (string) $session->aircraft_serial;
        }

        $fromTelemetry = DeviceTelemetry::query()
            ->where('member_id', $member->id)
            ->whereNotNull('aircraft_serial')
            ->latest()
            ->value('aircraft_serial');

        if (filled($fromTelemetry)) {
            return (string) $fromTelemetry;
        }

        $fromSession = FccSession::query()
            ->where('member_id', $member->id)
            ->whereNotNull('aircraft_serial')
            ->where('aircraft_serial', '!=', '')
            ->latest('id')
            ->value('aircraft_serial');

        return filled($fromSession) ? (string) $fromSession : null;
    }

    /**
     * @param  array{device_model?: string|null}  $location
     */
    protected function resolveDroneModel(Member $member, FccSession $session, array $location, ?string $serial): ?string
    {
        $fromPayload = $location['device_model'] ?? $session->device_model;
        if (filled($fromPayload) && ! $this->looksLikeControllerModel((string) $fromPayload)) {
            return (string) $fromPayload;
        }

        $telemetryQuery = DeviceTelemetry::query()
            ->where('member_id', $member->id)
            ->whereNotNull('drone_model')
            ->latest();

        if ($serial !== null) {
            $bySerial = (clone $telemetryQuery)
                ->where('aircraft_serial', $serial)
                ->value('drone_model');
            if (filled($bySerial) && ! $this->looksLikeControllerModel((string) $bySerial)) {
                return (string) $bySerial;
            }
        }

        $latest = $telemetryQuery->value('drone_model');
        if (filled($latest) && ! $this->looksLikeControllerModel((string) $latest)) {
            return (string) $latest;
        }

        if ($serial !== null) {
            return $this->inferDroneModel($serial);
        }

        return null;
    }

    protected function looksLikeControllerModel(string $value): bool
    {
        $lower = mb_strtolower($value);

        return str_contains($lower, 'dji rc')
            || str_contains($lower, 'rc331')
            || str_contains($lower, 'rc332')
            || str_contains($lower, 'rc151')
            || str_contains($lower, 'rc-n')
            || preg_match('/^rc\\d/i', $value) === 1;
    }

    protected function inferDroneModel(string $serial): ?string
    {
        if (strlen($serial) < 4) {
            return null;
        }

        return match (true) {
            str_starts_with($serial, '1581F') => 'DJI Mini Series',
            str_starts_with($serial, '1581U') => 'DJI Mavic Series',
            str_starts_with($serial, '1581W') => 'DJI Air Series',
            str_starts_with($serial, 'WA') => 'DJI Avata',
            str_starts_with($serial, 'WM') => 'DJI Mavic',
            default => null,
        };
    }

    /**
     * @param  array{latitude?: float|null, longitude?: float|null, province?: string|null, district?: string|null, neighborhood?: string|null}  $location
     */
    protected function locationLabel(Member $member, array $location): string
    {
        $parts = array_filter([
            $location['province'] ?? null,
            $location['district'] ?? null,
            $location['neighborhood'] ?? null,
        ], fn ($v) => filled($v));

        if ($parts !== []) {
            return implode(' / ', $parts);
        }

        $lat = $location['latitude'] ?? null;
        $lng = $location['longitude'] ?? null;

        if ($lat === null || $lng === null) {
            $telemetry = DeviceTelemetry::query()
                ->where('member_id', $member->id)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->latest()
                ->first();

            $lat = $telemetry?->latitude;
            $lng = $telemetry?->longitude;
        }

        if ($lat === null || $lng === null) {
            $sessionLoc = FccSession::query()
                ->where('member_id', $member->id)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->latest('id')
                ->first();

            $lat = $sessionLoc?->latitude;
            $lng = $sessionLoc?->longitude;
        }

        if ($lat !== null && $lng !== null) {
            $resolved = $this->reverseGeocode((float) $lat, (float) $lng);
            if ($resolved !== null) {
                return $resolved;
            }

            return sprintf('%.5f, %.5f', $lat, $lng);
        }

        return 'Konum alınamadı';
    }

    protected function reverseGeocode(float $lat, float $lng): ?string
    {
        $cacheKey = sprintf('geo.%s.%s', round($lat, 3), round($lng, 3));

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($lat, $lng) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'FreeFCC-FlightNotify/1.0',
                    'Accept-Language' => 'tr',
                ])->timeout(8)->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $lat,
                    'lon' => $lng,
                    'zoom' => 18,
                    'addressdetails' => 1,
                ]);

                if (! $response->successful()) {
                    return null;
                }

                $address = $response->json('address') ?? [];
                $parts = array_filter([
                    $address['province'] ?? $address['state'] ?? $address['region'] ?? null,
                    $address['district'] ?? $address['county'] ?? $address['town'] ?? $address['city'] ?? null,
                    $address['suburb'] ?? $address['neighbourhood'] ?? $address['quarter'] ?? $address['village'] ?? null,
                ], fn ($v) => filled($v));

                return $parts !== [] ? implode(' / ', $parts) : null;
            } catch (\Throwable) {
                return null;
            }
        });
    }

    protected function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '—';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return "{$hours} sa {$minutes} dk";
        }

        if ($minutes > 0) {
            return "{$minutes} dk {$secs} sn";
        }

        return "{$secs} sn";
    }
}
