<?php

namespace App\Filament\Resources\FeatureUsageLogs\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FeatureUsageLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                TextColumn::make('member.username')
                    ->label('Üye')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('feature')
                    ->label('Özellik')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'fcc_enable' => 'FCC Etkinleştir',
                        'fcc_disable' => 'CE Geri Yükle',
                        'keepalive_start' => 'Keepalive Başlat',
                        'keepalive_stop' => 'Keepalive Durdur',
                        'auto_fcc' => 'Otomatik FCC',
                        '4g_activate' => '4G Etkinleştir',
                        'led_on' => 'LED Aç',
                        'led_off' => 'LED Kapat',
                        'device_info' => 'Cihaz Bilgisi',
                        'connect' => 'Bağlan',
                        'dji_fly_launch' => 'DJI Fly Başlat',
                        'tab_switch' => 'Sekme Değiştir',
                        'auto_fcc_toggle' => 'Otomatik FCC Toggle',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'fcc_enable', 'auto_fcc' => 'success',
                        'fcc_disable' => 'danger',
                        '4g_activate' => 'warning',
                        'led_on', 'led_off' => 'info',
                        default => 'gray',
                    })
                    ->searchable(),
                IconColumn::make('success')
                    ->label('Başarılı')
                    ->boolean(),
                TextColumn::make('metadata')
                    ->label('Detay')
                    ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_UNESCAPED_UNICODE) : '—')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('feature')
                    ->label('Özellik')
                    ->options([
                        'fcc_enable' => 'FCC Etkinleştir',
                        'fcc_disable' => 'CE Geri Yükle',
                        'keepalive_start' => 'Keepalive Başlat',
                        'keepalive_stop' => 'Keepalive Durdur',
                        'auto_fcc' => 'Otomatik FCC',
                        'auto_fcc_toggle' => 'Otomatik FCC Toggle',
                        '4g_activate' => '4G Etkinleştir',
                        'led_on' => 'LED Aç',
                        'led_off' => 'LED Kapat',
                        'device_info' => 'Cihaz Bilgisi',
                        'connect' => 'Bağlan',
                        'dji_fly_launch' => 'DJI Fly Başlat',
                        'tab_switch' => 'Sekme Değiştir',
                    ]),
                TernaryFilter::make('success')
                    ->label('Başarılı mı?'),
            ]);
    }
}
