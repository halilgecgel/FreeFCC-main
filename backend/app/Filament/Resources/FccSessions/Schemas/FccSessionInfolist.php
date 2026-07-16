<?php

namespace App\Filament\Resources\FccSessions\Schemas;

use App\Models\FccSession;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FccSessionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Uçuş Özeti')
                    ->icon('heroicon-o-paper-airplane')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('member.username')
                            ->label('Üye')
                            ->placeholder('—'),
                        TextEntry::make('flight_status')
                            ->label('Durum')
                            ->badge()
                            ->state(fn (FccSession $record) => $record->flightStatus())
                            ->formatStateUsing(fn (string $state): string => FccSession::flightStatusLabel($state))
                            ->color(fn (string $state): string => FccSession::flightStatusColor($state)),
                        TextEntry::make('action')
                            ->label('Başlatma')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'auto_fcc' => 'Otomatik',
                                default => 'Manuel',
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'auto_fcc' => 'info',
                                default => 'success',
                            }),
                        TextEntry::make('flight_started_at')
                            ->label('Uçuş Başlangıcı')
                            ->state(function (FccSession $record) {
                                [$start] = $record->resolveFlightWindow();

                                return $start->copy()->addSeconds(5);
                            })
                            ->dateTime('d.m.Y H:i:s'),
                        TextEntry::make('flight_ended_at')
                            ->label('Uçuş Bitişi')
                            ->state(function (FccSession $record) {
                                if ($record->isOngoingFlight()) {
                                    return null;
                                }

                                [, $end] = $record->resolveFlightWindow();

                                return $end->copy()->subSeconds(5);
                            })
                            ->dateTime('d.m.Y H:i:s')
                            ->placeholder('Devam ediyor'),
                        TextEntry::make('flight_duration')
                            ->label('Uçuş Süresi')
                            ->state(fn (FccSession $record) => FccSession::formatDuration($record->flightDurationSeconds())),
                    ]),

                Section::make('Cihaz & Konum')
                    ->icon('heroicon-o-map-pin')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('aircraft_serial')
                            ->label('Uçak S/N')
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('device_model')
                            ->label('Cihaz / Drone')
                            ->placeholder('—'),
                        TextEntry::make('controller_model')
                            ->label('Kumanda')
                            ->placeholder('—'),
                        TextEntry::make('location')
                            ->label('Konum')
                            ->state(fn (FccSession $record) => $record->locationLabel())
                            ->columnSpan(2),
                        TextEntry::make('coordinates')
                            ->label('Koordinat')
                            ->state(function (FccSession $record) {
                                if ($record->latitude === null || $record->longitude === null) {
                                    return null;
                                }

                                return sprintf('%.6f, %.6f', $record->latitude, $record->longitude);
                            })
                            ->placeholder('—')
                            ->copyable(),
                    ]),

                Grid::make(4)
                    ->schema([
                        TextEntry::make('activity_count')
                            ->label('Uçuştaki App Günlükleri')
                            ->state(fn (FccSession $record) => $record->appActivityLogsDuringFlight()->count())
                            ->badge()
                            ->color('primary'),
                        TextEntry::make('errors_count')
                            ->label('Uçuştaki Hatalar')
                            ->state(fn (FccSession $record) => $record->errorLogsDuringFlight()->count())
                            ->badge()
                            ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                        TextEntry::make('usages_count')
                            ->label('Uçuştaki Kullanımlar')
                            ->state(fn (FccSession $record) => $record->featureUsageLogsDuringFlight()->count())
                            ->badge()
                            ->color('info'),
                        TextEntry::make('events_count')
                            ->label('Uçuştaki FCC Olayları')
                            ->state(fn (FccSession $record) => $record->eventsDuringFlight()->count())
                            ->badge()
                            ->color('gray'),
                    ]),
            ]);
    }
}
