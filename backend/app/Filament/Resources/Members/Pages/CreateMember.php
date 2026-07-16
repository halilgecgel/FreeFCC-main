<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use App\Services\WhatsAppService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    protected ?string $plainPassword = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['password'])) {
            $this->plainPassword = $data['password'];
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $member = $this->record;

        if ($member->phone && $this->plainPassword) {
            $message = "🛩️ *FreeFCC Hesap Bilgileriniz*\n\n"
                . "👤 Kullanıcı Adı: `{$member->username}`\n"
                . "🔑 Şifre: `{$this->plainPassword}`\n\n"
                . "İyi uçuşlar! ✈️";

            $sent = WhatsAppService::send($member->phone, $message);

            if ($sent) {
                Notification::make()
                    ->title('WhatsApp mesajı gönderildi')
                    ->body("Hesap bilgileri {$member->phone} numarasına WhatsApp ile gönderildi.")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('WhatsApp mesajı gönderilemedi')
                    ->body('Hesap bilgileri gönderilemedi. Lütfen manuel olarak iletin.')
                    ->warning()
                    ->send();
            }
        }
    }
}
