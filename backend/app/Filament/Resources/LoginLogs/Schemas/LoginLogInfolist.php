<?php

namespace App\Filament\Resources\LoginLogs\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class LoginLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('member.name')
                    ->label('Üye')
                    ->placeholder('-'),
                TextEntry::make('username')
                    ->label('Kullanıcı Adı'),
                TextEntry::make('device_id')
                    ->label('Cihaz Kimliği')
                    ->placeholder('-'),
                TextEntry::make('ip_address')
                    ->label('IP Adresi')
                    ->placeholder('-'),
                TextEntry::make('user_agent')
                    ->label('Cihaz/Uygulama')
                    ->placeholder('-'),
                IconEntry::make('success')
                    ->label('Başarılı')
                    ->boolean(),
                TextEntry::make('reason')
                    ->label('Sonuç')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i:s')
                    ->placeholder('-'),
            ]);
    }
}
