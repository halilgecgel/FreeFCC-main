<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Filament\Resources\FccSessions\FccSessionResource;
use App\Models\FccSession;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FccSessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'fccSessions';

    protected static ?string $title = 'Uçuş Geçmişi';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->flightStarts())
            ->recordUrl(fn (FccSession $record) => FccSessionResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Başlangıç')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('flight_ended_at')
                    ->label('Bitiş')
                    ->state(function (FccSession $record) {
                        if ($record->isOngoingFlight()) {
                            return null;
                        }

                        return $record->resolveFlightEndEvent()?->created_at;
                    })
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Devam ediyor'),
                TextColumn::make('flight_duration')
                    ->label('Süre')
                    ->state(fn (FccSession $record) => FccSession::formatDuration($record->flightDurationSeconds())),
                TextColumn::make('flight_status')
                    ->label('Durum')
                    ->badge()
                    ->state(fn (FccSession $record) => $record->flightStatus())
                    ->formatStateUsing(fn (string $state): string => FccSession::flightStatusLabel($state))
                    ->color(fn (string $state): string => FccSession::flightStatusColor($state)),
                TextColumn::make('device_model')
                    ->label('Cihaz / Drone')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('location')
                    ->label('Konum')
                    ->state(fn (FccSession $record) => $record->locationLabel())
                    ->toggleable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Detay')
                    ->url(fn (FccSession $record) => FccSessionResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Henüz uçuş yok')
            ->emptyStateDescription('Üyenin FCC etkinleştir → durdur aralıkları burada listelenir. Satıra tıklayarak olay detaylarını açın.');
    }
}
