<?php

namespace App\Filament\Resources\FeatureUsageLogs;

use App\Filament\Resources\FeatureUsageLogs\Pages\ListFeatureUsageLogs;
use App\Filament\Resources\FeatureUsageLogs\Tables\FeatureUsageLogsTable;
use App\Models\FeatureUsageLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FeatureUsageLogResource extends Resource
{
    protected static ?string $model = FeatureUsageLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCursorArrowRays;

    protected static ?string $modelLabel = 'Özellik Kullanımı';

    protected static ?string $pluralModelLabel = 'Özellik Kullanımları';

    protected static ?string $navigationLabel = 'Özellik Kullanımı';

    protected static string|UnitEnum|null $navigationGroup = 'Telemetri';

    public static function table(Table $table): Table
    {
        return FeatureUsageLogsTable::configure($table);
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
            'index' => ListFeatureUsageLogs::route('/'),
        ];
    }
}
