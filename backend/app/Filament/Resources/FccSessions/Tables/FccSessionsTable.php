<?php

namespace App\Filament\Resources\FccSessions\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FccSessionsTable
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
                TextColumn::make('action')
                    ->label('İşlem')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'fcc_enable' => 'FCC Etkinleştir',
                        'fcc_disable' => 'FCC Durdur',
                        'keepalive_start' => 'Keepalive Başlat',
                        'keepalive_stop' => 'Keepalive Durdur',
                        'auto_fcc' => 'Otomatik FCC',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'fcc_enable', 'auto_fcc' => 'success',
                        'fcc_disable' => 'danger',
                        'keepalive_start' => 'info',
                        'keepalive_stop' => 'warning',
                        default => 'gray',
                    }),
                IconColumn::make('success')
                    ->label('Başarılı')
                    ->boolean(),
                TextColumn::make('duration_seconds')
                    ->label('Süre')
                    ->formatStateUsing(function ($state) {
                        if (! $state) return '—';
                        $hours = intdiv($state, 3600);
                        $minutes = intdiv($state % 3600, 60);
                        $seconds = $state % 60;
                        if ($hours > 0) return "{$hours}s {$minutes}dk";
                        if ($minutes > 0) return "{$minutes}dk {$seconds}sn";
                        return "{$seconds}sn";
                    })
                    ->toggleable(),
                TextColumn::make('keepalive_count')
                    ->label('Keepalive')
                    ->toggleable(),
                TextColumn::make('ce_reset_blocks')
                    ->label('CE Engel')
                    ->toggleable(),
                TextColumn::make('aircraft_serial')
                    ->label('Uçak S/N')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('controller_model')
                    ->label('Kumanda')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('failure_reason')
                    ->label('Hata')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('İşlem')
                    ->options([
                        'fcc_enable' => 'FCC Etkinleştir',
                        'fcc_disable' => 'FCC Durdur',
                        'keepalive_start' => 'Keepalive Başlat',
                        'keepalive_stop' => 'Keepalive Durdur',
                        'auto_fcc' => 'Otomatik FCC',
                    ]),
                TernaryFilter::make('success')
                    ->label('Başarılı mı?'),
            ]);
    }
}
