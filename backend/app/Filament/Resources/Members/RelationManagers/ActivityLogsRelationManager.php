<?php

namespace App\Filament\Resources\Members\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActivityLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'activityLogs';

    protected static ?string $title = 'Aktivite Geçmişi';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'online' => 'Çevrimiçi',
                        'offline' => 'Çevrimdışı',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'online' => 'success',
                        'offline' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('started_at')
                    ->label('Başlangıç')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                TextColumn::make('ended_at')
                    ->label('Bitiş')
                    ->dateTime('d.m.Y H:i:s')
                    ->placeholder('Devam ediyor'),
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
                    }),
                TextColumn::make('ip_address')
                    ->label('IP Adresi'),
                TextColumn::make('created_at')
                    ->label('Kayıt Tarihi')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
