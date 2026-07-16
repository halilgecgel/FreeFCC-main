<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * SMS gönderir. .env dosyasında SMS_PROVIDER ayarlarını yapılandırın.
     *
     * Desteklenen sağlayıcılar: netgsm, vatansms, iletimerkezi
     * Veya SMS_PROVIDER=log olarak ayarlayarak sadece loglama yapabilirsiniz.
     */
    public static function send(string $phone, string $message): bool
    {
        $provider = config('services.sms.provider', 'log');

        return match ($provider) {
            'netgsm' => static::sendViaNetgsm($phone, $message),
            'vatansms' => static::sendViaVatanSms($phone, $message),
            'log' => static::sendViaLog($phone, $message),
            default => static::sendViaLog($phone, $message),
        };
    }

    private static function sendViaNetgsm(string $phone, string $message): bool
    {
        try {
            $response = Http::get('https://api.netgsm.com.tr/sms/send/get', [
                'usercode' => config('services.sms.netgsm.usercode'),
                'password' => config('services.sms.netgsm.password'),
                'gsmno' => static::normalizePhone($phone),
                'msgheader' => config('services.sms.netgsm.header', 'FREEFCC'),
                'dession' => $message,
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('NetGSM SMS hatası', ['error' => $e->getMessage(), 'phone' => $phone]);
            return false;
        }
    }

    private static function sendViaVatanSms(string $phone, string $message): bool
    {
        try {
            $response = Http::post('https://api.vatansms.net/api/v1/1toN', [
                'api_id' => config('services.sms.vatansms.api_id'),
                'api_key' => config('services.sms.vatansms.api_key'),
                'sender' => config('services.sms.vatansms.sender', 'FREEFCC'),
                'message_type' => 'normal',
                'message' => $message,
                'phones' => [static::normalizePhone($phone)],
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('VatanSMS hatası', ['error' => $e->getMessage(), 'phone' => $phone]);
            return false;
        }
    }

    private static function sendViaLog(string $phone, string $message): bool
    {
        Log::info('SMS (log modu)', ['phone' => $phone, 'message' => $message]);
        return true;
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
