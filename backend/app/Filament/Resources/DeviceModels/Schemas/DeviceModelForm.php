<?php

namespace App\Filament\Resources\DeviceModels\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class DeviceModelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Model Bilgileri')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Model Adı')
                            ->required()
                            ->maxLength(191)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, $state, callable $set) {
                                if ($operation === 'create' && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(191)
                            ->helperText('Uygulama tarafında teknik kimlik olarak kullanılır.'),
                        Textarea::make('description')
                            ->label('Açıklama')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('İsteğe bağlı. Uygulamada model seçim ekranında gösterilir.'),
                        TextInput::make('sort_order')
                            ->label('Sıra')
                            ->numeric()
                            ->default(0)
                            ->helperText('Küçük sayı önce listelenir.'),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->helperText('Kapatılırsa uygulamada seçilemez.'),
                    ]),
            ]);
    }
}
