<?php

namespace App\Filament\Resources\Members\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FccSessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'fccSessions';

    protected static ?string $title = 'FCC Oturumları';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
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
                        if ($hours > 0) return "{$hours}s {$minutes}dk";
                        if ($minutes > 0) return "{$minutes}dk";
                        return "{$state}sn";
                    }),
                TextColumn::make('keepalive_count')
                    ->label('Keepalive'),
                TextColumn::make('created_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
