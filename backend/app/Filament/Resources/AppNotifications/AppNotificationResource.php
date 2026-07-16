<?php

namespace App\Filament\Resources\AppNotifications;

use App\Filament\Resources\AppNotifications\Pages\CreateAppNotification;
use App\Filament\Resources\AppNotifications\Pages\EditAppNotification;
use App\Filament\Resources\AppNotifications\Pages\ListAppNotifications;
use App\Filament\Resources\AppNotifications\Schemas\AppNotificationForm;
use App\Filament\Resources\AppNotifications\Tables\AppNotificationsTable;
use App\Models\AppNotification;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AppNotificationResource extends Resource
{
    protected static ?string $model = AppNotification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static ?string $modelLabel = 'Bildirim';

    protected static ?string $pluralModelLabel = 'Bildirimler';

    protected static ?string $navigationLabel = 'Bildirimler';

    protected static string|UnitEnum|null $navigationGroup = 'Uygulama';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return AppNotificationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AppNotificationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppNotifications::route('/'),
            'create' => CreateAppNotification::route('/create'),
            'edit' => EditAppNotification::route('/{record}/edit'),
        ];
    }
}
