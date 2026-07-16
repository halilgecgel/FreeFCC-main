<?php

namespace App\Filament\Resources\FccSessions;

use App\Filament\Resources\FccSessions\Pages\ListFccSessions;
use App\Filament\Resources\FccSessions\Pages\ViewFccSession;
use App\Filament\Resources\FccSessions\Schemas\FccSessionInfolist;
use App\Filament\Resources\FccSessions\Tables\FccSessionsTable;
use App\Models\FccSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class FccSessionResource extends Resource
{
    protected static ?string $model = FccSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $modelLabel = 'Uçuş';

    protected static ?string $pluralModelLabel = 'Uçuş Geçmişi';

    protected static ?string $navigationLabel = 'Uçuş Geçmişi';

    protected static string|UnitEnum|null $navigationGroup = 'Telemetri';

    public static function infolist(Schema $schema): Schema
    {
        return FccSessionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FccSessionsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->flightStarts();
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
            'view' => ViewFccSession::route('/{record}'),
        ];
    }
}
