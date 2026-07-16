<?php

namespace App\Filament\Pages;

use App\Services\EvolutionApiService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class WhatsApp extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $navigationLabel = 'WhatsApp Bağlantısı';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.whats-app';

    public bool $isConfigured = false;

    public string $instanceName = '';

    public string $state = 'unknown';

    public ?string $qrCode = null;

    public ?string $phone = null;

    public ?string $profileName = null;

    public ?string $profilePicture = null;

    public function getTitle(): string
    {
        return 'WhatsApp Bağlantısı';
    }

    public function getSubheading(): ?string
    {
        return 'Evolution API üzerinden bir WhatsApp numarası bağlayın.';
    }

    public function mount(): void
    {
        $this->refreshStatus();
    }

    public function refreshStatus(): void
    {
        $service = app(EvolutionApiService::class);

        $this->isConfigured = $service->isConfigured();
        $this->instanceName = $service->instanceName();

        if (! $this->isConfigured) {
            $this->state = 'not_configured';

            return;
        }

        try {
            $details = $service->connectionDetails();

            $this->state = $details['state'];
            $this->phone = $details['phone'];
            $this->profileName = $details['profile_name'];
            $this->profilePicture = $details['profile_picture'];

            if ($this->state === 'open') {
                $this->qrCode = null;
            }
        } catch (\Throwable $e) {
            $this->state = 'error';

            Notification::make()
                ->title('Durum alınamadı')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function connect(): void
    {
        $service = app(EvolutionApiService::class);

        try {
            $payload = $service->connect();
            $qr = $service->extractQrCode($payload);

            if ($qr) {
                $this->qrCode = $qr;
                $this->state = 'connecting';
            } else {
                Notification::make()
                    ->title('QR kod alınamadı')
                    ->body('Evolution API bir QR kod döndürmedi. Kısa süre sonra tekrar deneyin.')
                    ->warning()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Bağlantı başlatılamadı')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->refreshStatus();
    }

    public function disconnect(): void
    {
        $service = app(EvolutionApiService::class);

        try {
            $service->logout();
            $this->qrCode = null;

            Notification::make()
                ->title('WhatsApp bağlantısı kesildi')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Bağlantı kesilemedi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->refreshStatus();
    }
}
