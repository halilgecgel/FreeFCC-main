<?php

namespace App\Filament\Resources\AppReleases\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AppReleaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sürüm Bilgileri')
                    ->columns(2)
                    ->components([
                        TextInput::make('version')
                            ->label('Sürüm')
                            ->placeholder('1.5.0')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('version_code')
                            ->label('Sürüm Kodu (versionCode)')
                            ->numeric()
                            ->required()
                            ->helperText('Android build.gradle\'daki versionCode değeri'),
                        TextInput::make('title')
                            ->label('Başlık')
                            ->placeholder('v1.5 - Yeni Özellikler')
                            ->required()
                            ->maxLength(191)
                            ->columnSpanFull(),
                        Textarea::make('changelog')
                            ->label('Değişiklik Notları')
                            ->rows(5)
                            ->columnSpanFull(),
                    ]),

                Section::make('APK Dosyası')
                    ->components([
                        FileUpload::make('apk_path')
                            ->label('APK Dosyası')
                            ->required()
                            ->disk('public')
                            ->directory('releases')
                            ->acceptedFileTypes([
                                'application/vnd.android.package-archive',
                                'application/octet-stream',
                                'application/x-authorware-bin',
                                'application/java-archive',
                            ])
                            ->maxSize(204800) // 200 MB in KB
                            ->visibility('public')
                            ->helperText('Maksimum 200 MB. Yüklenen APK public storage\'da saklanır.'),
                        TextInput::make('sha256')
                            ->label('SHA-256 Hash')
                            ->placeholder('İsteğe bağlı — APK bütünlük doğrulaması için')
                            ->maxLength(64),
                    ]),

                Section::make('Zorunlu Güncelleme Ayarları')
                    ->columns(2)
                    ->description('Güncellemenin kullanıcılar için zorunlu olup olmadığını ayarlayın.')
                    ->components([
                        Toggle::make('is_force')
                            ->label('Hemen Zorunlu')
                            ->helperText('Açılırsa güncelleme yayınlandığı anda zorunlu olur.')
                            ->reactive(),
                        TextInput::make('force_after_hours')
                            ->label('Kaç Saat Sonra Zorunlu Olsun')
                            ->numeric()
                            ->placeholder('Örn: 72 (3 gün)')
                            ->helperText('Yayın tarihinden bu kadar saat sonra güncelleme zorunlu olur. Boş bırakılırsa opsiyonel kalır.')
                            ->visible(fn ($get) => ! $get('is_force')),
                        TextInput::make('min_supported_version')
                            ->label('Minimum Desteklenen Sürüm')
                            ->placeholder('1.4.0')
                            ->helperText('Bu sürümün altındaki kullanıcılar zorunlu güncelleme alır.')
                            ->maxLength(50),
                    ]),

                Section::make('Yayın Ayarları')
                    ->columns(2)
                    ->components([
                        DateTimePicker::make('published_at')
                            ->label('Yayın Tarihi')
                            ->default(now())
                            ->native(false)
                            ->helperText('İleri bir tarih ayarlarsanız güncelleme o tarihe kadar görünmez.'),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->helperText('Kapatılırsa güncelleme kullanıcılara gösterilmez.'),
                    ]),
            ]);
    }
}
