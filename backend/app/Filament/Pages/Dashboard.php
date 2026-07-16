<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AppOverview;
use App\Filament\Widgets\FeatureUsageChart;
use App\Filament\Widgets\LatestErrorsWidget;
use App\Filament\Widgets\LatestLoginsWidget;
use App\Filament\Widgets\LoginsChart;
use App\Filament\Widgets\OnlineMembersWidget;
use App\Filament\Widgets\SessionsChart;
use App\Filament\Widgets\StatsOverview;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Panel';

    protected static ?string $title = 'Panel Özeti';

    public function getSubheading(): ?string
    {
        return 'Üye, oturum, giriş ve hata istatistiklerinin canlı özeti';
    }

    /**
     * @return int | array<string, ?int>
     */
    public function getColumns(): int | array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 3,
        ];
    }

    /**
     * @return array<class-string | \Filament\Widgets\WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            StatsOverview::class,
            SessionsChart::class,
            LoginsChart::class,
            FeatureUsageChart::class,
            OnlineMembersWidget::class,
            LatestErrorsWidget::class,
            LatestLoginsWidget::class,
            AppOverview::class,
            AccountWidget::class,
        ];
    }
}
