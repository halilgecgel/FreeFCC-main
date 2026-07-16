<?php

namespace App\Filament\Widgets;

use App\Models\LoginLog;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class LoginsChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Giriş Aktivitesi';

    protected ?string $description = 'Başarılı ve başarısız giriş denemeleri';

    protected ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = 1;

    public ?string $filter = '14';

    protected function getFilters(): ?array
    {
        return [
            '7' => 'Son 7 gün',
            '14' => 'Son 14 gün',
            '30' => 'Son 30 gün',
        ];
    }

    protected function getData(): array
    {
        $days = (int) ($this->filter ?? 14);
        $labels = [];
        $success = [];
        $failed = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $labels[] = $date->format('d.m');

            $success[] = LoginLog::query()
                ->whereDate('created_at', $date)
                ->where('success', true)
                ->count();

            $failed[] = LoginLog::query()
                ->whereDate('created_at', $date)
                ->where('success', false)
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Başarılı',
                    'data' => $success,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.75)',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Başarısız',
                    'data' => $failed,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.75)',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'x' => [
                    'stacked' => true,
                ],
                'y' => [
                    'stacked' => true,
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}
