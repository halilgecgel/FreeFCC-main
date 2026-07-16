<?php

namespace App\Filament\Resources\FccSessions\Widgets;

use App\Models\AppActivityLog;
use App\Models\FccSession;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class FlightActivityLogsWidget extends TableWidget
{
    public ?FccSession $record = null;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Uçuş Boyunca Uygulama Günlüğü')
            ->description('Mobil uygulamada bu uçuş sırasında görünen tüm aktivite satırları')
            ->query(fn (): Builder => $this->flightActivityQuery())
            ->columns([
                TextColumn::make('created_at')
                    ->label('Zaman')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                TextColumn::make('level')
                    ->label('Seviye')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'info' => 'Bilgi',
                        'warn' => 'Uyarı',
                        'error' => 'Hata',
                        'debug' => 'Debug',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'error' => 'danger',
                        'warn' => 'warning',
                        'debug' => 'gray',
                        default => 'info',
                    }),
                TextColumn::make('message')
                    ->label('Mesaj')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('app_version')
                    ->label('Sürüm')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->defaultSort('created_at', 'asc')
            ->emptyStateHeading('Bu uçuşta günlük yok')
            ->emptyStateDescription('Uçuş süresince uygulamadan gelen aktivite kaydı bulunamadı.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }

    protected function flightActivityQuery(): Builder
    {
        if (! $this->record) {
            return AppActivityLog::query()->whereRaw('1 = 0');
        }

        return $this->record->appActivityLogsDuringFlight();
    }
}
