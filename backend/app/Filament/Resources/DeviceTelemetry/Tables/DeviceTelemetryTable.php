<?php

namespace App\Filament\Resources\DeviceTelemetry\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeviceTelemetryTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                TextColumn::make('member.username')
                    ->label('Üye')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('controller_model')
                    ->label('Kumanda')
                    ->searchable(),
                TextColumn::make('firmware_version')
                    ->label('Firmware')
                    ->toggleable(),
                TextColumn::make('hardware_version')
                    ->label('Donanım')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('aircraft_serial')
                    ->label('Uçak S/N')
                    ->searchable(),
                TextColumn::make('drone_model')
                    ->label('Drone Modeli')
                    ->toggleable(),
                TextColumn::make('detected_port')
                    ->label('Port')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('android_version')
                    ->label('Android')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('app_version')
                    ->label('Uygulama')
                    ->toggleable(),
                TextColumn::make('network_type')
                    ->label('Ağ')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('country_code')
                    ->label('Ülke')
                    ->toggleable(),
                TextColumn::make('locale')
                    ->label('Dil')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('latitude')
                    ->label('Enlem')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('longitude')
                    ->label('Boylam')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('server_ping_ms')
                    ->label('Ping (ms)')
                    ->toggleable(),
            ]);
    }
}
