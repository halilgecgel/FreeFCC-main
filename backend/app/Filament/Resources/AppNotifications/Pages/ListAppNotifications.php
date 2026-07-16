<?php

namespace App\Filament\Resources\AppNotifications\Pages;

use App\Filament\Resources\AppNotifications\AppNotificationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAppNotifications extends ListRecords
{
    protected static string $resource = AppNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
