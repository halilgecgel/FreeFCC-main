<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\LoginLogs\LoginLogResource;
use App\Models\LoginLog;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestLoginsWidget extends TableWidget
{
    protected static ?int $sort = 7;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Son Girişler')
            ->description('Başarılı ve başarısız giriş denemeleri')
            ->headerActions([
                Action::make('all')
                    ->label('Tümünü Gör')
                    ->url(LoginLogResource::getUrl('index'))
                    ->icon('heroicon-m-arrow-top-right-on-square'),
            ])
            ->query(
                fn (): Builder => LoginLog::query()->latest('created_at')
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label('Zaman')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('username')
                    ->label('Kullanıcı')
                    ->searchable()
                    ->weight('medium'),
                IconColumn::make('success')
                    ->label('Sonuç')
                    ->boolean(),
                TextColumn::make('reason')
                    ->label('Detay')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'ok' => 'success',
                        'device_mismatch' => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'ok' => 'Başarılı',
                        'invalid_credentials' => 'Hatalı Bilgi',
                        'inactive' => 'Pasif Hesap',
                        'expired' => 'Süresi Dolmuş',
                        'device_mismatch' => 'Cihaz Uyuşmazlığı',
                        default => $state ?? '—',
                    }),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->placeholder('—'),
                TextColumn::make('device_id')
                    ->label('Cihaz')
                    ->limit(16)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Giriş kaydı yok')
            ->emptyStateDescription('Henüz giriş denemesi kaydedilmedi.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }
}
