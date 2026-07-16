<?php

namespace App\Filament\Resources\ErrorLogs\Pages;

use App\Filament\Resources\ErrorLogs\ErrorLogResource;
use Filament\Resources\Pages\ListRecords;

class ListErrorLogs extends ListRecords
{
    protected static string $resource = ErrorLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
