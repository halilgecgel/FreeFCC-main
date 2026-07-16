<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public static function send(string $phone, string $message): bool
    {
        $baseUrl = rtrim(config('services.evolution.url'), '/');
        $apiKey = config('services.evolution.key');
        $instance = config('services.evolution.instance');

        if (! $apiKey || ! $instance) {
            Log::warning('WhatsApp: Evolution API yapılandırması eksik');
            return false;
        }

        $number = static::normalizePhone($phone);

        try {
            $response = Http::withHeaders([
                'apikey' => $apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$baseUrl}/message/sendText/{$instance}", [
                'number' => $number,
                'text' => $message,
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('WhatsApp mesaj gönderilemedi', [
                'phone' => $number,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('WhatsApp bağlantı hatası', [
                'error' => $e->getMessage(),
                'phone' => $number,
            ]);
            return false;
        }
    }

    private static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '90') && strlen($phone) === 12) {
            return $phone;
        }

        if (str_starts_with($phone, '0') && strlen($phone) === 11) {
            return '9' . $phone;
        }

        if (strlen($phone) === 10) {
            return '90' . $phone;
        }

        return $phone;
    }
}
