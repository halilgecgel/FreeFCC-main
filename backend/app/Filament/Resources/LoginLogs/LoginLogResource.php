<?php

namespace App\Filament\Resources\LoginLogs;

use App\Filament\Resources\LoginLogs\Pages\ListLoginLogs;
use App\Filament\Resources\LoginLogs\Pages\ViewLoginLog;
use App\Filament\Resources\LoginLogs\Schemas\LoginLogInfolist;
use App\Filament\Resources\LoginLogs\Tables\LoginLogsTable;
use App\Models\LoginLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Read-only audit trail — login attempts are only ever created by the API,
 * never through the admin panel, so create/edit are intentionally disabled.
 */
class LoginLogResource extends Resource
{
    protected static ?string $model = LoginLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'Giriş Kaydı';

    protected static ?string $pluralModelLabel = 'Giriş Günlüğü';

    protected static ?string $navigationLabel = 'Giriş Günlüğü';

    public static function infolist(Schema $schema): Schema
    {
        return LoginLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LoginLogsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoginLogs::route('/'),
            'view' => ViewLoginLog::route('/{record}'),
        ];
    }
}
