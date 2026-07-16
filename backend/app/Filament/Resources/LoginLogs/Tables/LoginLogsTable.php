<?php

namespace App\Filament\Resources\LoginLogs\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class LoginLogsTable
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
                TextColumn::make('username')
                    ->label('Kullanıcı Adı')
                    ->searchable(),
                IconColumn::make('success')
                    ->label('Başarılı')
                    ->boolean(),
                TextColumn::make('reason')
                    ->label('Sonuç')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ok' => 'success',
                        'device_mismatch' => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ok' => 'Başarılı',
                        'invalid_credentials' => 'Hatalı Bilgi',
                        'inactive' => 'Pasif Hesap',
                        'expired' => 'Süresi Dolmuş',
                        'device_mismatch' => 'Cihaz Uyuşmazlığı',
                        default => $state,
                    }),
                TextColumn::make('device_id')
                    ->label('Cihaz Kimliği')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ip_address')
                    ->label('IP Adresi')
                    ->searchable(),
                TextColumn::make('user_agent')
                    ->label('Cihaz/Uygulama')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(40),
            ])
            ->filters([
                TernaryFilter::make('success')
                    ->label('Başarılı mı?'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
