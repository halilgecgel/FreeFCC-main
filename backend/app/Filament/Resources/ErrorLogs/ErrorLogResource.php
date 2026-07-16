<?php

namespace App\Filament\Resources\ErrorLogs;

use App\Filament\Resources\ErrorLogs\Pages\ListErrorLogs;
use App\Filament\Resources\ErrorLogs\Tables\ErrorLogsTable;
use App\Models\ErrorLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ErrorLogResource extends Resource
{
    protected static ?string $model = ErrorLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $modelLabel = 'Hata Kaydı';

    protected static ?string $pluralModelLabel = 'Hata Kayıtları';

    protected static ?string $navigationLabel = 'Hata Kayıtları';

    protected static string|UnitEnum|null $navigationGroup = 'Telemetri';

    public static function table(Table $table): Table
    {
        return ErrorLogsTable::configure($table);
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
            'index' => ListErrorLogs::route('/'),
        ];
    }
}
