<?php

namespace App\Filament\Resources\AppNotifications\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AppNotificationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bildirim İçeriği')
                    ->columns(2)
                    ->components([
                        TextInput::make('title')
                            ->label('Başlık')
                            ->required()
                            ->maxLength(191)
                            ->columnSpanFull(),
                        Textarea::make('message')
                            ->label('Mesaj')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                        Select::make('type')
                            ->label('Tip')
                            ->options([
                                'info' => 'Bilgi',
                                'warning' => 'Uyarı',
                                'update' => 'Güncelleme',
                                'promo' => 'Duyuru',
                            ])
                            ->default('info')
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->helperText('Kapatılırsa bildirim kullanıcılara gösterilmez.'),
                    ]),
            ]);
    }
}
