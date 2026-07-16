<?php

namespace App\Filament\Resources\DeviceTelemetry\Pages;

use App\Filament\Resources\DeviceTelemetry\DeviceTelemetryResource;
use Filament\Resources\Pages\ListRecords;

class ListDeviceTelemetry extends ListRecords
{
    protected static string $resource = DeviceTelemetryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
