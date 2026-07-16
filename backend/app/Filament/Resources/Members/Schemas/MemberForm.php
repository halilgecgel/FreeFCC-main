<?php

namespace App\Filament\Resources\Members\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Hesap Bilgileri')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Ad Soyad')
                            ->maxLength(191),
                        TextInput::make('username')
                            ->label('Kullanıcı Adı')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(191),
                        TextInput::make('password')
                            ->label('Şifre')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('Mevcut bir üyeyi düzenlerken boş bırakırsanız şifre değişmez.'),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->helperText('Kapatılırsa üye giriş yapamaz.'),
                        DateTimePicker::make('expires_at')
                            ->label('Üyelik Bitiş Tarihi')
                            ->helperText('Boş bırakılırsa üyeliğin süresi dolmaz.')
                            ->native(false),
                    ]),

                Section::make('Cihaz Kilidi')
                    ->columns(2)
                    ->description('Üye ilk giriş yaptığında cihazı otomatik kilitlenir. Cihazı değiştirmesi gerekiyorsa buradan sıfırlayın.')
                    ->components([
                        TextInput::make('device_id')
                            ->label('Kayıtlı Cihaz Kimliği (ANDROID_ID)')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Henüz giriş yapmadı'),
                        DateTimePicker::make('device_registered_at')
                            ->label('Cihaz Kayıt Tarihi')
                            ->disabled()
                            ->dehydrated(false)
                            ->native(false),
                    ]),

                Section::make('Diğer')
                    ->components([
                        Textarea::make('notes')
                            ->label('Notlar')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
