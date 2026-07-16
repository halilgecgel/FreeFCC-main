<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewMember extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetDevice')
                ->label('Cihazı Sıfırla')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('warning')
                ->visible(fn () => filled($this->record->device_id))
                ->requiresConfirmation()
                ->modalHeading('Cihaz Sıfırlama')
                ->modalDescription('Bu üyenin cihaz kilidi kaldırılacak ve aktif oturumu sonlandırılacak. Üye bir dahaki girişte yeni bir cihazdan giriş yapabilecek.')
                ->action(function () {
                    $this->record->resetDevice();
                    Notification::make()
                        ->title('Cihaz kaydı sıfırlandı')
                        ->success()
                        ->send();
                }),
            EditAction::make()
                ->label('Düzenle'),
            DeleteAction::make()
                ->label('Sil'),
        ];
    }
}
