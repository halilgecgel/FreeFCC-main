<?php

namespace App\Filament\Resources\DeviceTelemetry;

use App\Filament\Resources\DeviceTelemetry\Pages\ListDeviceTelemetry;
use App\Filament\Resources\DeviceTelemetry\Tables\DeviceTelemetryTable;
use App\Models\DeviceTelemetry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DeviceTelemetryResource extends Resource
{
    protected static ?string $model = DeviceTelemetry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static ?string $modelLabel = 'Cihaz Telemetrisi';

    protected static ?string $pluralModelLabel = 'Cihaz Telemetrisi';

    protected static ?string $navigationLabel = 'Cihaz Telemetrisi';

    protected static string|UnitEnum|null $navigationGroup = 'Telemetri';

    public static function table(Table $table): Table
    {
        return DeviceTelemetryTable::configure($table);
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
            'index' => ListDeviceTelemetry::route('/'),
        ];
    }
}
