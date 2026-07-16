<?php

namespace App\Filament\Resources\FccSessions\Widgets;

use App\Models\FccSession;
use App\Models\ErrorLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class FlightErrorLogsWidget extends TableWidget
{
    public ?FccSession $record = null;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Uçuş Boyunca Hatalar')
            ->description('Bu uçuş zaman aralığında kaydedilen tüm hata mesajları')
            ->query(fn (): Builder => $this->flightErrorsQuery())
            ->columns([
                TextColumn::make('created_at')
                    ->label('Zaman')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                TextColumn::make('error_type')
                    ->label('Tip')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'connection' => 'warning',
                        'duml', 'crc', 'crash' => 'danger',
                        'fcc', 'keepalive' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('message')
                    ->label('Mesaj')
                    ->wrap()
                    ->limit(120),
                TextColumn::make('context')
                    ->label('Bağlam')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('app_version')
                    ->label('Sürüm')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('controller_model')
                    ->label('Kumanda')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stack_trace')
                    ->label('Stack')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Bu uçuşta hata yok')
            ->emptyStateDescription('Uçuş süresince kayıtlı hata mesajı bulunamadı.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    protected function flightErrorsQuery(): Builder
    {
        if (! $this->record) {
            return ErrorLog::query()->whereRaw('1 = 0');
        }

        return $this->record->errorLogsDuringFlight();
    }
}
