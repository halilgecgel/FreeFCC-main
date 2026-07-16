<?php

namespace App\Filament\Resources\Members\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MemberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        Group::make([
                            Section::make('Hesap Bilgileri')
                                ->icon('heroicon-o-user-circle')
                                ->schema([
                                    TextEntry::make('name')
                                        ->label('Ad Soyad')
                                        ->placeholder('Belirtilmemiş')
                                        ->icon('heroicon-m-user'),
                                    TextEntry::make('username')
                                        ->label('Kullanıcı Adı')
                                        ->icon('heroicon-m-at-symbol')
                                        ->copyable(),
                                    TextEntry::make('phone')
                                        ->label('Telefon')
                                        ->placeholder('Belirtilmemiş')
                                        ->icon('heroicon-m-phone'),
                                    TextEntry::make('status_label')
                                        ->label('Hesap Durumu')
                                        ->badge()
                                        ->color(fn (string $state): string => match ($state) {
                                            'Aktif' => 'success',
                                            'Süresi Doldu' => 'warning',
                                            default => 'danger',
                                        }),
                                    TextEntry::make('expires_at')
                                        ->label('Üyelik Bitiş')
                                        ->dateTime('d.m.Y H:i')
                                        ->placeholder('Süresiz')
                                        ->icon('heroicon-m-calendar'),
                                    TextEntry::make('notes')
                                        ->label('Notlar')
                                        ->placeholder('—')
                                        ->columnSpanFull(),
                                ])
                                ->columns(2),
                        ])->columnSpan(2),

                        Group::make([
                            Section::make('Çevrimiçi Durumu')
                                ->icon('heroicon-o-signal')
                                ->schema([
                                    IconEntry::make('is_online')
                                        ->label('Durum')
                                        ->boolean()
                                        ->trueIcon('heroicon-s-signal')
                                        ->falseIcon('heroicon-o-signal-slash')
                                        ->trueColor('success')
                                        ->falseColor('danger'),
                                    TextEntry::make('last_heartbeat_at')
                                        ->label('Son Sinyal')
                                        ->since()
                                        ->placeholder('Hiç bağlanmadı'),
                                    TextEntry::make('total_online_seconds')
                                        ->label('Toplam Kullanım')
                                        ->formatStateUsing(function ($state) {
                                            if (! $state) return '—';
                                            $hours = intdiv($state, 3600);
                                            $minutes = intdiv($state % 3600, 60);
                                            return "{$hours}s {$minutes}dk";
                                        }),
                                ]),

                            Section::make('Son Giriş')
                                ->icon('heroicon-o-arrow-right-end-on-rectangle')
                                ->schema([
                                    TextEntry::make('last_login_at')
                                        ->label('Tarih')
                                        ->dateTime('d.m.Y H:i')
                                        ->placeholder('Hiç giriş yapmadı'),
                                    TextEntry::make('last_login_ip')
                                        ->label('IP Adresi')
                                        ->placeholder('—')
                                        ->copyable(),
                                    TextEntry::make('app_version')
                                        ->label('Uygulama Sürümü')
                                        ->placeholder('—'),
                                ]),
                        ])->columnSpan(1),
                    ]),

                Section::make('Cihaz Bilgileri')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->columns(3)
                    ->schema([
                        IconEntry::make('device_id')
                            ->label('Cihaz Kayıtlı')
                            ->boolean()
                            ->getStateUsing(fn ($record) => filled($record->device_id)),
                        TextEntry::make('device_id')
                            ->label('Cihaz Kimliği (ANDROID_ID)')
                            ->placeholder('Henüz giriş yapmadı')
                            ->copyable(),
                        TextEntry::make('device_registered_at')
                            ->label('Kayıt Tarihi')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                    ]),

                Section::make('Tarihler')
                    ->icon('heroicon-o-clock')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Kayıt Tarihi')
                            ->dateTime('d.m.Y H:i'),
                        TextEntry::make('updated_at')
                            ->label('Son Güncelleme')
                            ->dateTime('d.m.Y H:i'),
                    ]),
            ]);
    }
}
