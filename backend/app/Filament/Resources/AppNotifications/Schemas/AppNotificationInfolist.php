<?php

namespace App\Filament\Resources\AppNotifications\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AppNotificationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bildirim')
                    ->columns(2)
                    ->components([
                        TextEntry::make('title')
                            ->label('Başlık')
                            ->columnSpanFull(),
                        TextEntry::make('message')
                            ->label('Mesaj')
                            ->columnSpanFull(),
                        TextEntry::make('type')
                            ->label('Tip')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'info' => 'Bilgi',
                                'warning' => 'Uyarı',
                                'update' => 'Güncelleme',
                                'promo' => 'Duyuru',
                                default => $state,
                            }),
                        IconEntry::make('is_active')
                            ->label('Aktif')
                            ->boolean(),
                        TextEntry::make('created_at')
                            ->label('Oluşturulma')
                            ->dateTime('d.m.Y H:i'),
                    ]),
                Section::make('İstatistik')
                    ->columns(3)
                    ->components([
                        TextEntry::make('delivered_receipts_count')
                            ->label('Ulaşan')
                            ->state(fn ($record) => $record->receipts()->whereNotNull('delivered_at')->count()),
                        TextEntry::make('read_receipts_count')
                            ->label('Okuyan')
                            ->state(fn ($record) => $record->receipts()->whereNotNull('read_at')->count()),
                        TextEntry::make('pending_read_count')
                            ->label('Ulaştı / Okunmadı')
                            ->state(fn ($record) => $record->receipts()
                                ->whereNotNull('delivered_at')
                                ->whereNull('read_at')
                                ->count()),
                    ]),
            ]);
    }
}
