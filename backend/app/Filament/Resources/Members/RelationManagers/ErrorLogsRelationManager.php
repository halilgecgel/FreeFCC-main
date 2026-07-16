<?php

namespace App\Filament\Resources\Members\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ErrorLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'errorLogs';

    protected static ?string $title = 'Hata Kayıtları';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('error_type')
                    ->label('Tip')
                    ->badge()
                    ->color('danger'),
                TextColumn::make('message')
                    ->label('Mesaj')
                    ->limit(80),
                TextColumn::make('context')
                    ->label('Bağlam'),
                TextColumn::make('app_version')
                    ->label('Sürüm'),
                TextColumn::make('created_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
