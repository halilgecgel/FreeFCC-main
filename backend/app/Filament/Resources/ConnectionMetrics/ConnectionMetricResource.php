<?php

namespace App\Filament\Resources\ConnectionMetrics;

use App\Filament\Resources\ConnectionMetrics\Pages\ListConnectionMetrics;
use App\Filament\Resources\ConnectionMetrics\Tables\ConnectionMetricsTable;
use App\Models\ConnectionMetric;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConnectionMetricResource extends Resource
{
    protected static ?string $model = ConnectionMetric::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $modelLabel = 'Bağlantı Metriği';

    protected static ?string $pluralModelLabel = 'Bağlantı Metrikleri';

    protected static ?string $navigationLabel = 'Bağlantı Metrikleri';

    protected static ?string $navigationGroup = 'Telemetri';

    public static function table(Table $table): Table
    {
        return ConnectionMetricsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConnectionMetrics::route('/'),
        ];
    }
}
