<?php

namespace App\Filament\Resources\FccSessions\Widgets;

use App\Models\FccSession;
use App\Models\FeatureUsageLog;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class FlightFeatureUsagesWidget extends TableWidget
{
    public ?FccSession $record = null;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Uçuş Boyunca Özellik Kullanımları')
            ->description('Bu uçuş zaman aralığında yapılan tüm özellik kullanımları')
            ->query(fn (): Builder => $this->flightUsagesQuery())
            ->columns([
                TextColumn::make('created_at')
                    ->label('Zaman')
                    ->dateTime('d.m.Y H:i:s')
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
                    }),
                IconColumn::make('success')
                    ->label('Başarılı')
                    ->boolean(),
                TextColumn::make('metadata')
                    ->label('Detay')
                    ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_UNESCAPED_UNICODE) : '—')
                    ->limit(80)
                    ->wrap(),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Bu uçuşta kullanım yok')
            ->emptyStateDescription('Uçuş süresince kayıtlı özellik kullanımı bulunamadı.')
            ->emptyStateIcon('heroicon-o-cube');
    }

    protected function flightUsagesQuery(): Builder
    {
        if (! $this->record) {
            return FeatureUsageLog::query()->whereRaw('1 = 0');
        }

        return $this->record->featureUsageLogsDuringFlight();
    }
}
