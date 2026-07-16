<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AppNotifications\AppNotificationResource;
use App\Filament\Resources\AppReleases\AppReleaseResource;
use App\Models\AppNotification;
use App\Models\AppRelease;
use App\Models\ConnectionMetric;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AppOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 8;

    protected ?string $heading = 'Uygulama Durumu';

    protected ?string $description = 'Sürüm, bildirim ve bağlantı özeti';

    protected ?string $pollingInterval = '120s';

    protected function getStats(): array
    {
        $latestRelease = AppRelease::query()
            ->where('is_active', true)
            ->orderByDesc('version_code')
            ->first();

        $activeNotifications = AppNotification::query()->where('is_active', true)->count();

        $avgConnectMs = ConnectionMetric::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->avg('connect_time_ms');

        $avgLatencyMs = ConnectionMetric::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->avg('command_latency_ms');

        $crcErrors = (int) ConnectionMetric::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->sum('crc_error_count');

        return [
            Stat::make('Güncel Sürüm', $latestRelease?->version ?? 'Yok')
                ->description($latestRelease
                    ? ($latestRelease->is_force ? 'Zorunlu güncelleme aktif' : 'Yayında')
                    : 'Aktif sürüm tanımlanmamış')
                ->descriptionIcon($latestRelease?->is_force ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-arrow-up-tray')
                ->descriptionColor($latestRelease?->is_force ? 'warning' : 'success')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->url(AppReleaseResource::getUrl('index')),

            Stat::make('Aktif Bildirimler', number_format($activeNotifications))
                ->description($activeNotifications > 0 ? 'Uygulamada gösteriliyor' : 'Aktif bildirim yok')
                ->descriptionIcon('heroicon-m-bell')
                ->descriptionColor($activeNotifications > 0 ? 'info' : 'gray')
                ->icon('heroicon-o-bell')
                ->color('info')
                ->url(AppNotificationResource::getUrl('index')),

            Stat::make('Ort. Bağlantı', $avgConnectMs !== null ? number_format((float) $avgConnectMs, 0).' ms' : '—')
                ->description('Son 7 gün bağlantı süresi')
                ->descriptionIcon('heroicon-m-bolt')
                ->icon('heroicon-o-bolt')
                ->color('warning'),

            Stat::make('Ort. Komut Gecikmesi', $avgLatencyMs !== null ? number_format((float) $avgLatencyMs, 0).' ms' : '—')
                ->description($crcErrors > 0 ? "{$crcErrors} CRC hatası (7 gün)" : 'CRC hatası yok (7 gün)')
                ->descriptionIcon($crcErrors > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
                ->descriptionColor($crcErrors > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-chart-bar')
                ->color($crcErrors > 0 ? 'danger' : 'success'),
        ];
    }
}
