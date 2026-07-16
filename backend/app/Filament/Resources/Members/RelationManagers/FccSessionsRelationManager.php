<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Filament\Resources\FccSessions\FccSessionResource;
use App\Models\FccSession;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FccSessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'fccSessions';

    protected static ?string $title = 'Aktivite Geçmişi';

    public function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (FccSession $record) => FccSessionResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('action')
                    ->label('İşlem')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => FccSession::actionLabel($state))
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
                    ->formatStateUsing(fn ($state) => FccSession::formatDuration($state ? (int) $state : null)),
                TextColumn::make('keepalive_count')
                    ->label('Keepalive'),
                TextColumn::make('aircraft_serial')
                    ->label('Uçak S/N')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('location')
                    ->label('Konum')
                    ->state(fn (FccSession $record) => $record->locationLabel())
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Detay')
                    ->url(fn (FccSession $record) => FccSessionResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Henüz uçuş yok')
            ->emptyStateDescription('Üyenin FCC oturum / uçuş kayıtları burada listelenir. Satıra tıklayarak detayı açın.');
    }
}
