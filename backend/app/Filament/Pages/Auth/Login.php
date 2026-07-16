<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use SensitiveParameter;

/**
 * Admin panel login, extended to accept either an email address or a
 * username in the same field (mobile app Members already log in with a
 * username via the API — this mirrors that for admin panel `User`s).
 */
class Login extends BaseLogin
{
    public function getTitle(): string|Htmlable
    {
        return config('app.name').' — Giriş Yap';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Yönetim Paneline Hoş Geldiniz';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Devam etmek için hesap bilgilerinizi girin.';
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('E-posta veya Kullanıcı Adı')
            ->placeholder('ornek@sirket.com veya kullaniciadi')
            ->required()
            ->autocomplete()
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $login = $data['email'];
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        return [
            $field => $login,
            'password' => $data['password'],
        ];
    }
}
