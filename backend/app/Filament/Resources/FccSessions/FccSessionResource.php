<?php

namespace App\Filament\Resources\FccSessions;

use App\Filament\Resources\FccSessions\Pages\ListFccSessions;
use App\Filament\Resources\FccSessions\Tables\FccSessionsTable;
use App\Models\FccSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FccSessionResource extends Resource
{
    protected static ?string $model = FccSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $modelLabel = 'FCC Oturumu';

    protected static ?string $pluralModelLabel = 'FCC Oturumları';

    protected static ?string $navigationLabel = 'FCC Oturumları';

    protected static string|UnitEnum|null $navigationGroup = 'Telemetri';

    public static function table(Table $table): Table
    {
        return FccSessionsTable::configure($table);
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
            'index' => ListFccSessions::route('/'),
        ];
    }
}
