<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ErrorLogs\ErrorLogResource;
use App\Filament\Resources\FccSessions\FccSessionResource;
use App\Filament\Resources\LoginLogs\LoginLogResource;
use App\Filament\Resources\Members\MemberResource;
use App\Models\ErrorLog;
use App\Models\FccSession;
use App\Models\LoginLog;
use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $totalMembers = Member::query()->count();
        $onlineMembers = Member::query()->currentlyOnline()->count();
        $activeMembers = Member::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();
        $expiredMembers = Member::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->count();

        $sessionsToday = FccSession::query()->whereDate('created_at', today())->count();
        $sessionsSuccessToday = FccSession::query()
            ->whereDate('created_at', today())
            ->where('success', true)
            ->count();
        $sessionSuccessRate = $sessionsToday > 0
            ? (int) round(($sessionsSuccessToday / $sessionsToday) * 100)
            : 100;

        $loginsToday = LoginLog::query()->whereDate('created_at', today())->count();
        $failedLoginsToday = LoginLog::query()
            ->whereDate('created_at', today())
            ->where('success', false)
            ->count();

        $errorsToday = ErrorLog::query()->whereDate('created_at', today())->count();
        $errorsYesterday = ErrorLog::query()->whereDate('created_at', today()->subDay())->count();
        $errorDelta = $errorsToday - $errorsYesterday;

        $memberChart = $this->dailyCounts(Member::class, 7);
        $sessionChart = $this->dailyCounts(FccSession::class, 7);
        $loginChart = $this->dailyCounts(LoginLog::class, 7);
        $errorChart = $this->dailyCounts(ErrorLog::class, 7);

        return [
            Stat::make('Toplam Üye', number_format($totalMembers))
                ->description("{$activeMembers} aktif · {$expiredMembers} süresi dolmuş")
                ->descriptionIcon('heroicon-m-user-group')
                ->descriptionColor('gray')
                ->icon('heroicon-o-user-group')
                ->chart($memberChart)
                ->color('primary')
                ->url(MemberResource::getUrl('index')),

            Stat::make('Çevrimiçi', number_format($onlineMembers))
                ->description($totalMembers > 0
                    ? '%'.(int) round(($onlineMembers / $totalMembers) * 100).' üye bağlı'
                    : 'Henüz üye yok')
                ->descriptionIcon('heroicon-m-signal')
                ->descriptionColor($onlineMembers > 0 ? 'success' : 'gray')
                ->icon('heroicon-o-signal')
                ->color('success')
                ->url(MemberResource::getUrl('index')),

            Stat::make('FCC Oturumları (Bugün)', number_format($sessionsToday))
                ->description("%{$sessionSuccessRate} başarılı")
                ->descriptionIcon($sessionSuccessRate >= 80 ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
                ->descriptionColor($sessionSuccessRate >= 80 ? 'success' : 'warning')
                ->icon('heroicon-o-signal')
                ->chart($sessionChart)
                ->color('info')
                ->url(FccSessionResource::getUrl('index')),

            Stat::make('Giriş Denemeleri (Bugün)', number_format($loginsToday))
                ->description($failedLoginsToday > 0
                    ? "{$failedLoginsToday} başarısız"
                    : 'Başarısız giriş yok')
                ->descriptionIcon($failedLoginsToday > 0 ? 'heroicon-m-x-circle' : 'heroicon-m-check-circle')
                ->descriptionColor($failedLoginsToday > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-arrow-right-end-on-rectangle')
                ->chart($loginChart)
                ->color('warning')
                ->url(LoginLogResource::getUrl('index')),

            Stat::make('Hatalar (Bugün)', number_format($errorsToday))
                ->description($errorDelta === 0
                    ? 'Düne göre değişmedi'
                    : ($errorDelta > 0 ? "+{$errorDelta} dünden fazla" : abs($errorDelta).' dünden az'))
                ->descriptionIcon($errorDelta > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->descriptionColor($errorDelta > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle')
                ->chart($errorChart)
                ->color($errorsToday > 0 ? 'danger' : 'success')
                ->url(ErrorLogResource::getUrl('index')),

            Stat::make('Aktif Üyeler', number_format($activeMembers))
                ->description($expiredMembers > 0
                    ? "{$expiredMembers} üyenin süresi dolmuş"
                    : 'Süresi dolmuş üye yok')
                ->descriptionIcon('heroicon-m-shield-check')
                ->descriptionColor($expiredMembers > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->url(MemberResource::getUrl('index')),
        ];
    }

    /**
     * @return array<int, float>
     */
    protected function dailyCounts(string $model, int $days): array
    {
        $counts = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $counts[] = (float) $model::query()->whereDate('created_at', $date)->count();
        }

        return $counts;
    }
}
