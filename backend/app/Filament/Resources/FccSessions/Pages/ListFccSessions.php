<?php

namespace App\Filament\Resources\FccSessions\Pages;

use App\Filament\Resources\FccSessions\FccSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListFccSessions extends ListRecords
{
    protected static string $resource = FccSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
