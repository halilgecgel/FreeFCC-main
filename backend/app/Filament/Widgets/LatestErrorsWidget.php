<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ErrorLogs\ErrorLogResource;
use App\Models\ErrorLog;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestErrorsWidget extends TableWidget
{
    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = 2;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Son Hatalar')
            ->description('Uygulamadan gelen en güncel hata kayıtları')
            ->headerActions([
                Action::make('all')
                    ->label('Tümünü Gör')
                    ->url(ErrorLogResource::getUrl('index'))
                    ->icon('heroicon-m-arrow-top-right-on-square'),
            ])
            ->query(
                fn (): Builder => ErrorLog::query()
                    ->with('member')
                    ->latest()
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label('Zaman')
                    ->since()
                    ->sortable(),
                TextColumn::make('member.username')
                    ->label('Üye')
                    ->placeholder('—')
                    ->weight('medium'),
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
                    ->limit(40)
                    ->wrap(),
            ])
            ->paginated([5])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Hata kaydı yok')
            ->emptyStateDescription('Son dönemde raporlanan hata bulunmuyor.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
