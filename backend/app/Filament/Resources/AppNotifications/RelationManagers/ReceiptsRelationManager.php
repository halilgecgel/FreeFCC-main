<?php

namespace App\Filament\Resources\AppNotifications\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReceiptsRelationManager extends RelationManager
{
    protected static string $relationship = 'receipts';

    protected static ?string $title = 'Teslim ve Okunma';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member.name')
                    ->label('Üye')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('member.username')
                    ->label('Kullanıcı Adı')
                    ->searchable(),
                IconColumn::make('is_delivered')
                    ->label('Ulaştı')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->delivered_at !== null),
                TextColumn::make('delivered_at')
                    ->label('Ulaşma Zamanı')
                    ->dateTime('d.m.Y H:i:s')
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('is_read')
                    ->label('Okundu')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->read_at !== null),
                TextColumn::make('read_at')
                    ->label('Okunma Zamanı')
                    ->dateTime('d.m.Y H:i:s')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('delivered_at', 'desc')
            ->paginated([25, 50, 100]);
    }
}
