<?php

namespace App\Filament\Resources\FccSessions\Tables;

use App\Filament\Resources\FccSessions\FccSessionResource;
use App\Models\FccSession;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FccSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (FccSession $record) => FccSessionResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Başlangıç')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                TextColumn::make('flight_ended_at')
                    ->label('Bitiş')
                    ->state(function (FccSession $record) {
                        if ($record->isOngoingFlight()) {
                            return null;
                        }

                        return $record->resolveFlightEndEvent()?->created_at;
                    })
                    ->dateTime('d.m.Y H:i:s')
                    ->placeholder('Devam ediyor'),
                TextColumn::make('flight_duration')
                    ->label('Süre')
                    ->state(fn (FccSession $record) => FccSession::formatDuration($record->flightDurationSeconds())),
                TextColumn::make('member.username')
                    ->label('Üye')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('flight_status')
                    ->label('Durum')
                    ->badge()
                    ->state(fn (FccSession $record) => $record->flightStatus())
                    ->formatStateUsing(fn (string $state): string => FccSession::flightStatusLabel($state))
                    ->color(fn (string $state): string => FccSession::flightStatusColor($state)),
                TextColumn::make('action')
                    ->label('Başlatma')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'auto_fcc' => 'Otomatik',
                        default => 'Manuel',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'auto_fcc' => 'info',
                        default => 'success',
                    })
                    ->toggleable(),
                TextColumn::make('device_model')
                    ->label('Cihaz / Drone')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('location')
                    ->label('Konum')
                    ->state(fn (FccSession $record) => $record->locationLabel())
                    ->toggleable(),
                TextColumn::make('events_count')
                    ->label('Olay')
                    ->state(fn (FccSession $record) => $record->eventsDuringFlight()->count())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('aircraft_serial')
                    ->label('Uçak S/N')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('controller_model')
                    ->label('Kumanda')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('Başlatma')
                    ->options([
                        'fcc_enable' => 'Manuel',
                        'auto_fcc' => 'Otomatik',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Detay'),
            ])
            ->emptyStateHeading('Henüz uçuş yok')
            ->emptyStateDescription('FCC etkinleştirildiğinde burada bir uçuş kaydı oluşur. Detayda o aralıktaki tüm olaylar görünür.');
    }
}
