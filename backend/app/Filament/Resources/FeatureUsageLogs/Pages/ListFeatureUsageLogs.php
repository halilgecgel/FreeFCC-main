<?php

namespace App\Filament\Resources\FeatureUsageLogs\Pages;

use App\Filament\Resources\FeatureUsageLogs\FeatureUsageLogResource;
use Filament\Resources\Pages\ListRecords;

class ListFeatureUsageLogs extends ListRecords
{
    protected static string $resource = FeatureUsageLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
