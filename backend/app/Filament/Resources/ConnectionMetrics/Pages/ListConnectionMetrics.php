<?php

namespace App\Filament\Resources\ConnectionMetrics\Pages;

use App\Filament\Resources\ConnectionMetrics\ConnectionMetricResource;
use Filament\Resources\Pages\ListRecords;

class ListConnectionMetrics extends ListRecords
{
    protected static string $resource = ConnectionMetricResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
