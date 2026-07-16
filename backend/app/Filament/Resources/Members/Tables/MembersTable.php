<?php

namespace App\Filament\Resources\Members\Tables;

use App\Filament\Resources\Members\MemberResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('is_online')
                    ->label('Çevrimiçi')
                    ->boolean()
                    ->trueIcon('heroicon-s-signal')
                    ->falseIcon('heroicon-o-signal-slash')
                    ->trueColor('success')
                    ->falseColor('danger'),
                TextColumn::make('username')
                    ->label('Kullanıcı Adı')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Ad Soyad')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('phone')
                    ->label('Telefon')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status_label')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Aktif' => 'success',
                        'Süresi Doldu' => 'warning',
                        default => 'danger',
                    }),
                IconColumn::make('device_id')
                    ->label('Cihaz Kayıtlı')
                    ->boolean()
                    ->getStateUsing(fn ($record) => filled($record->device_id)),
                TextColumn::make('expires_at')
                    ->label('Bitiş Tarihi')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Süresiz')
                    ->sortable(),
                TextColumn::make('last_heartbeat_at')
                    ->label('Son Sinyal')
                    ->since()
                    ->placeholder('Hiç bağlanmadı')
                    ->sortable(),
                TextColumn::make('total_online_seconds')
                    ->label('Toplam Kullanım')
                    ->formatStateUsing(function ($state) {
                        if (! $state) return '—';
                        $hours = intdiv($state, 3600);
                        $minutes = intdiv($state % 3600, 60);
                        return "{$hours}s {$minutes}dk";
                    })
                    ->sortable(),
                TextColumn::make('last_login_at')
                    ->label('Son Giriş')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Hiç giriş yapmadı')
                    ->sortable(),
                TextColumn::make('last_login_ip')
                    ->label('Son IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('app_version')
                    ->label('Uygulama Sürümü')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Aktif mi?'),
                TernaryFilter::make('is_online')
                    ->label('Çevrimiçi mi?'),
            ])
            ->defaultSort('is_online', 'desc')
            ->poll('10s')
            ->recordUrl(fn ($record) => MemberResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Detay'),
                    EditAction::make()
                        ->label('Düzenle'),
                    Action::make('resetDevice')
                        ->label('Cihazı Sıfırla')
                        ->icon('heroicon-o-device-phone-mobile')
                        ->color('warning')
                        ->visible(fn ($record) => filled($record->device_id))
                        ->requiresConfirmation()
                        ->modalHeading('Cihaz Sıfırlama')
                        ->modalDescription('Bu üyenin cihaz kilidi kaldırılacak ve aktif oturumu sonlandırılacak. Üye bir dahaki girişte yeni bir cihazdan giriş yapabilecek.')
                        ->action(function ($record) {
                            $record->resetDevice();

                            Notification::make()
                                ->title('Cihaz kaydı sıfırlandı')
                                ->success()
                                ->send();
                        }),
                    DeleteAction::make()
                        ->label('Sil'),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('İşlemler'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
