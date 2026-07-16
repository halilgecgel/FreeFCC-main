<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Members\MemberResource;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OnlineMembersWidget extends TableWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Çevrimiçi Üyeler')
            ->description('Şu anda bağlı olan üyeler')
            ->query(
                fn (): Builder => Member::query()
                    ->where('is_online', true)
                    ->orderByDesc('last_heartbeat_at')
            )
            ->columns([
                IconColumn::make('is_online')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-s-signal')
                    ->falseIcon('heroicon-o-signal-slash')
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('username')
                    ->label('Kullanıcı')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('app_version')
                    ->label('Sürüm')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('last_heartbeat_at')
                    ->label('Son Sinyal')
                    ->since()
                    ->placeholder('—'),
                TextColumn::make('last_login_ip')
                    ->label('IP')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Görüntüle')
                    ->icon('heroicon-m-eye')
                    ->url(fn (Member $record): string => MemberResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Çevrimiçi üye yok')
            ->emptyStateDescription('Bağlı üye olduğunda burada listelenir.')
            ->emptyStateIcon('heroicon-o-signal-slash');
    }
}
