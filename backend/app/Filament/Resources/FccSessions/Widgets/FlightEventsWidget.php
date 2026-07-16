<?php

namespace App\Filament\Resources\FccSessions\Widgets;

use App\Models\FccSession;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class FlightEventsWidget extends TableWidget
{
    public ?FccSession $record = null;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Uçuş Boyunca FCC Olayları')
            ->description('Enable / keepalive / disable dahil tüm oturum olayları')
            ->query(fn (): Builder => $this->flightEventsQuery())
            ->columns([
                TextColumn::make('created_at')
                    ->label('Zaman')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                TextColumn::make('action')
                    ->label('İşlem')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => FccSession::actionLabel($state))
                    ->color(fn (string $state): string => match ($state) {
                        'fcc_enable', 'auto_fcc' => 'success',
                        'fcc_disable' => 'danger',
                        'keepalive_start' => 'info',
                        'keepalive_stop' => 'warning',
                        default => 'gray',
                    }),
                IconColumn::make('success')
                    ->label('Başarılı')
                    ->boolean(),
                TextColumn::make('duration_seconds')
                    ->label('Süre')
                    ->formatStateUsing(fn ($state) => FccSession::formatDuration($state ? (int) $state : null)),
                TextColumn::make('keepalive_count')
                    ->label('Keepalive')
                    ->placeholder('—'),
                TextColumn::make('failure_reason')
                    ->label('Hata')
                    ->limit(50)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Olay yok')
            ->emptyStateDescription('Bu uçuş aralığında FCC oturum olayı bulunamadı.')
            ->emptyStateIcon('heroicon-o-signal');
    }

    protected function flightEventsQuery(): Builder
    {
        if (! $this->record) {
            return FccSession::query()->whereRaw('1 = 0');
        }

        return $this->record->eventsDuringFlight();
    }
}
