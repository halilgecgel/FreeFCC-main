<?php

namespace App\Filament\Resources\AppReleases;

use App\Filament\Resources\AppReleases\Pages\CreateAppRelease;
use App\Filament\Resources\AppReleases\Pages\EditAppRelease;
use App\Filament\Resources\AppReleases\Pages\ListAppReleases;
use App\Filament\Resources\AppReleases\Schemas\AppReleaseForm;
use App\Filament\Resources\AppReleases\Tables\AppReleasesTable;
use App\Models\AppRelease;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AppReleaseResource extends Resource
{
    protected static ?string $model = AppRelease::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $modelLabel = 'Güncelleme';

    protected static ?string $pluralModelLabel = 'Güncellemeler';

    protected static ?string $navigationLabel = 'Güncellemeler';

    protected static string|UnitEnum|null $navigationGroup = 'Uygulama';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return AppReleaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AppReleasesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppReleases::route('/'),
            'create' => CreateAppRelease::route('/create'),
            'edit' => EditAppRelease::route('/{record}/edit'),
        ];
    }
}
