<?php

namespace App\Filament\Resources\AppNotifications\Pages;

use App\Filament\Resources\AppNotifications\AppNotificationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAppNotification extends ViewRecord
{
    protected static string $resource = AppNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Düzenle'),
            DeleteAction::make()->label('Sil'),
        ];
    }
}
