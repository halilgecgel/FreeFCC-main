<?php

namespace App\Filament\Resources\AppNotifications\Pages;

use App\Filament\Resources\AppNotifications\AppNotificationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAppNotification extends EditRecord
{
    protected static string $resource = AppNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
