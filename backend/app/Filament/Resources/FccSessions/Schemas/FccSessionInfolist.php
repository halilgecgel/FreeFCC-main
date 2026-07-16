<?php

namespace App\Filament\Resources\FccSessions\Schemas;

use App\Models\FccSession;
use Filament\Infolists\Components\IconEntry;
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
                        TextEntry::make('action')
                            ->label('Bu Kayıt')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => FccSession::actionLabel($state))
                            ->color(fn (string $state): string => match ($state) {
                                'fcc_enable', 'auto_fcc' => 'success',
                                'fcc_disable' => 'danger',
                                default => 'gray',
                            }),
                        IconEntry::make('success')
                            ->label('Başarılı')
                            ->boolean(),
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
                        TextEntry::make('duration_seconds')
                            ->label('Kayıt Süresi')
                            ->formatStateUsing(fn ($state) => FccSession::formatDuration($state ? (int) $state : null))
                            ->placeholder('—'),
                        TextEntry::make('keepalive_count')
                            ->label('Keepalive')
                            ->placeholder('0'),
                        TextEntry::make('ce_reset_blocks')
                            ->label('CE Engel')
                            ->placeholder('0'),
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

                Section::make('Hata Bilgisi')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->visible(fn (FccSession $record) => filled($record->failure_reason) || ! $record->success)
                    ->schema([
                        TextEntry::make('failure_reason')
                            ->label('Başarısızlık Nedeni')
                            ->placeholder('—')
                            ->columnSpanFull(),
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
