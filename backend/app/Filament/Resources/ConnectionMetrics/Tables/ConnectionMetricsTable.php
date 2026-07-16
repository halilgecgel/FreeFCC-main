<?php

namespace App\Filament\Resources\ConnectionMetrics\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConnectionMetricsTable
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
                TextColumn::make('connect_time_ms')
                    ->label('Bağlantı (ms)')
                    ->sortable(),
                TextColumn::make('command_latency_ms')
                    ->label('Komut Gecikme (ms)')
                    ->sortable(),
                TextColumn::make('disconnection_count')
                    ->label('Kopma')
                    ->sortable(),
                TextColumn::make('crc_error_count')
                    ->label('CRC Hata')
                    ->sortable(),
                TextColumn::make('port_used')
                    ->label('Port')
                    ->toggleable(),
                TextColumn::make('controller_model')
                    ->label('Kumanda')
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}
