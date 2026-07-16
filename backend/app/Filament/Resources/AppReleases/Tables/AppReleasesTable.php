<?php

namespace App\Filament\Resources\AppReleases\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AppReleasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('version_code', 'desc')
            ->columns([
                TextColumn::make('version')
                    ->label('Sürüm')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn ($state) => "v{$state}"),
                TextColumn::make('version_code')
                    ->label('Kod')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Başlık')
                    ->searchable()
                    ->limit(40),
                IconColumn::make('is_force')
                    ->label('Zorunlu')
                    ->boolean(),
                TextColumn::make('force_after_hours')
                    ->label('Zorunlu Olma Süresi')
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state) => $state ? "{$state} saat" : null),
                TextColumn::make('apk_size')
                    ->label('Boyut')
                    ->formatStateUsing(fn ($state) => $state > 0 ? number_format($state / 1048576, 1) . ' MB' : '—'),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('published_at')
                    ->label('Yayın Tarihi')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
