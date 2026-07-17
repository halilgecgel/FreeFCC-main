<?php

namespace App\Filament\Resources\AppNotifications\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AppNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'receipts as delivered_count' => fn (Builder $q) => $q->whereNotNull('delivered_at'),
                'receipts as read_count' => fn (Builder $q) => $q->whereNotNull('read_at'),
            ]))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Başlık')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('type')
                    ->label('Tip')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'info' => 'info',
                        'warning' => 'warning',
                        'update' => 'success',
                        'promo' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'info' => 'Bilgi',
                        'warning' => 'Uyarı',
                        'update' => 'Güncelleme',
                        'promo' => 'Duyuru',
                        default => $state,
                    }),
                TextColumn::make('delivered_count')
                    ->label('Ulaşan')
                    ->sortable(),
                TextColumn::make('read_count')
                    ->label('Okuyan')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Oluşturulma')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make()->label('Detay'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
