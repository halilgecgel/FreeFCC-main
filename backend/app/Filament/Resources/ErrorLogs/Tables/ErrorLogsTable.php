<?php

namespace App\Filament\Resources\ErrorLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ErrorLogsTable
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
                TextColumn::make('error_type')
                    ->label('Hata Tipi')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'connection' => 'warning',
                        'duml', 'crc' => 'danger',
                        'fcc', 'keepalive' => 'info',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('message')
                    ->label('Mesaj')
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('context')
                    ->label('Bağlam')
                    ->toggleable(),
                TextColumn::make('controller_model')
                    ->label('Kumanda')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('app_version')
                    ->label('Sürüm')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('error_type')
                    ->label('Hata Tipi')
                    ->options([
                        'connection' => 'Bağlantı',
                        'duml' => 'DUML',
                        'crc' => 'CRC',
                        'fcc' => 'FCC',
                        'keepalive' => 'Keepalive',
                        '4g' => '4G',
                        'led' => 'LED',
                        'crash' => 'Çökme',
                        'other' => 'Diğer',
                    ]),
            ]);
    }
}
