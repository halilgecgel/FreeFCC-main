<?php

namespace App\Filament\Resources\FccSessions\Pages;

use App\Filament\Resources\FccSessions\FccSessionResource;
use App\Filament\Resources\FccSessions\Widgets\FlightActivityLogsWidget;
use App\Filament\Resources\FccSessions\Widgets\FlightErrorLogsWidget;
use App\Filament\Resources\FccSessions\Widgets\FlightEventsWidget;
use App\Filament\Resources\FccSessions\Widgets\FlightFeatureUsagesWidget;
use Filament\Resources\Pages\ViewRecord;

class ViewFccSession extends ViewRecord
{
    protected static string $resource = FccSessionResource::class;

    protected static ?string $title = 'Uçuş Detayı';

    protected function getFooterWidgets(): array
    {
        return [
            FlightActivityLogsWidget::class,
            FlightEventsWidget::class,
            FlightFeatureUsagesWidget::class,
            FlightErrorLogsWidget::class,
        ];
    }

    public function getFooterWidgetsColumns(): int | array
    {
        return 1;
    }
}
