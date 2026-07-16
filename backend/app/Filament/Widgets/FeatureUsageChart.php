<?php

namespace App\Filament\Widgets;

use App\Models\FeatureUsageLog;
use Filament\Widgets\ChartWidget;

class FeatureUsageChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Özellik Kullanımı';

    protected ?string $description = 'Son 7 günde en çok kullanılan özellikler';

    protected ?string $pollingInterval = '120s';

    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $rows = FeatureUsageLog::query()
            ->selectRaw('feature, count(*) as total')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('feature')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $labels = $rows->map(fn ($row) => $this->featureLabel((string) $row->feature))->all();
        $data = $rows->pluck('total')->map(fn ($v) => (int) $v)->all();

        if ($labels === []) {
            $labels = ['Veri yok'];
            $data = [0];
        }

        $palette = [
            'rgba(245, 158, 11, 0.85)',
            'rgba(59, 130, 246, 0.85)',
            'rgba(34, 197, 94, 0.85)',
            'rgba(168, 85, 247, 0.85)',
            'rgba(239, 68, 68, 0.85)',
            'rgba(20, 184, 166, 0.85)',
            'rgba(249, 115, 22, 0.85)',
            'rgba(99, 102, 241, 0.85)',
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Kullanım',
                    'data' => $data,
                    'backgroundColor' => array_slice($palette, 0, count($data)),
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
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
        ];
    }

    protected function featureLabel(string $feature): string
    {
        return match ($feature) {
            'fcc_enable' => 'FCC Aç',
            'fcc_disable' => 'FCC Kapat',
            'auto_fcc' => 'Otomatik FCC',
            'keepalive' => 'Keepalive',
            'ce_reset' => 'CE Reset',
            'led' => 'LED',
            '4g' => '4G',
            default => str_replace('_', ' ', ucfirst($feature)),
        };
    }
}
