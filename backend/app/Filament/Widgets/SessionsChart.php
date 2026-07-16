<?php

namespace App\Filament\Widgets;

use App\Models\FccSession;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class SessionsChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'FCC Oturumları';

    protected ?string $description = 'Son günlerdeki oturum ve başarı oranı';

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
        $total = [];
        $success = [];
        $failed = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $labels[] = $date->format('d.m');

            $dayQuery = FccSession::query()->whereDate('created_at', $date);
            $dayTotal = (clone $dayQuery)->count();
            $daySuccess = (clone $dayQuery)->where('success', true)->count();

            $total[] = $dayTotal;
            $success[] = $daySuccess;
            $failed[] = $dayTotal - $daySuccess;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Başarılı',
                    'data' => $success,
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
                [
                    'label' => 'Başarısız',
                    'data' => $failed,
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
                [
                    'label' => 'Toplam',
                    'data' => $total,
                    'borderColor' => 'rgb(245, 158, 11)',
                    'backgroundColor' => 'transparent',
                    'borderDash' => [4, 4],
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
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
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}
