<?php

namespace App\Filament\Resources\Members\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeviceTelemetryRelationManager extends RelationManager
{
    protected static string $relationship = 'deviceTelemetry';

    protected static ?string $title = 'Cihaz Telemetrisi';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('controller_model')
                    ->label('Kumanda'),
                TextColumn::make('firmware_version')
                    ->label('Firmware'),
                TextColumn::make('aircraft_serial')
                    ->label('Uçak S/N'),
                TextColumn::make('drone_model')
                    ->label('Drone'),
                TextColumn::make('detected_port')
                    ->label('Port'),
                TextColumn::make('app_version')
                    ->label('Uygulama'),
                TextColumn::make('network_type')
                    ->label('Ağ'),
                TextColumn::make('country_code')
                    ->label('Ülke'),
                TextColumn::make('server_ping_ms')
                    ->label('Ping'),
                TextColumn::make('created_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
