<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin client around a self-hosted Evolution API (WhatsApp) instance.
 *
 * Evolution API exposes one HTTP server that can manage many named
 * "instances" (each one is a separate WhatsApp session/device). This
 * service always operates on the single instance configured via
 * `services.evolution.instance`.
 */
class EvolutionApiService
{
    protected function config(): array
    {
        return [
            'url' => rtrim((string) config('services.evolution.url', ''), '/'),
            'key' => (string) config('services.evolution.key', ''),
            'instance' => (string) config('services.evolution.instance', ''),
        ];
    }

    public function isConfigured(): bool
    {
        $config = $this->config();

        return $config['url'] !== '' && $config['key'] !== '';
    }

    public function instanceName(): string
    {
        return $this->config()['instance'] ?: 'freefcc';
    }

    protected function client(): PendingRequest
    {
        $config = $this->config();

        if ($config['url'] === '') {
            throw new \RuntimeException('Evolution API adresi yapılandırılmamış.');
        }

        return Http::withHeaders([
            'apikey' => $config['key'],
            'Content-Type' => 'application/json',
        ])->baseUrl($config['url'])->timeout(45);
    }

    public function connectionState(?string $instance = null): array
    {
        $instance = $instance ?: $this->instanceName();

        $response = $this->send(fn (PendingRequest $client) => $client->get("/instance/connectionState/{$instance}"));

        if ($response->status() === 404) {
            return [
                'instance' => [
                    'instanceName' => $instance,
                    'state' => 'not_found',
                ],
            ];
        }

        $this->throwOnFailure($response);

        return $response->json() ?? [];
    }

    public function fetchInstances(?string $instance = null): array
    {
        $instance = $instance ?: $this->instanceName();

        $response = $this->send(fn (PendingRequest $client) => $client->get('/instance/fetchInstances', [
            'instanceName' => $instance,
        ]));

        $this->throwOnFailure($response);

        return $response->json() ?? [];
    }

    public function fetchOwnProfile(?string $instance = null): array
    {
        $instance = $instance ?: $this->instanceName();

        $response = $this->send(fn (PendingRequest $client) => $client->post("/chat/fetchProfile/{$instance}", []));

        $this->throwOnFailure($response);

        return $response->json() ?? [];
    }

    /**
     * @return array{state: string, instance: string, phone: ?string, profile_name: ?string, profile_picture: ?string}
     */
    public function connectionDetails(?string $instance = null): array
    {
        $instance = $instance ?: $this->instanceName();
        $state = $this->connectionState($instance)['instance']['state'] ?? 'unknown';

        $details = [
            'state' => $state,
            'instance' => $instance,
            'phone' => null,
            'profile_name' => null,
            'profile_picture' => null,
        ];

        if ($state !== 'open') {
            return $details;
        }

        try {
            $instances = $this->fetchInstances($instance);
            $info = is_array($instances) ? ($instances[0] ?? null) : null;

            if (is_array($info)) {
                $details['phone'] = $this->formatPhone($info['ownerJid'] ?? $info['number'] ?? null);
                $details['profile_name'] = $info['profileName'] ?? null;
                $details['profile_picture'] = $info['profilePicUrl'] ?? null;
            }
        } catch (\Throwable) {
            //
        }

        try {
            $profile = $this->fetchOwnProfile($instance);
            $details['phone'] = $details['phone'] ?: $this->formatPhone($profile['wuid'] ?? null);
            $details['profile_name'] = $profile['name'] ?? $details['profile_name'];
            $details['profile_picture'] = $profile['picture'] ?? $details['profile_picture'];
        } catch (\Throwable) {
            //
        }

        return $details;
    }

    protected function formatPhone(?string $jid): ?string
    {
        if (! $jid) {
            return null;
        }

        $digits = preg_replace('/\D/', '', explode('@', $jid)[0]);

        if (strlen($digits) === 12 && str_starts_with($digits, '90')) {
            return '+90 '.substr($digits, 2, 3).' '.substr($digits, 5, 3).' '.substr($digits, 8, 2).' '.substr($digits, 10, 2);
        }

        return $digits ?: null;
    }

    public function ensureInstance(?string $instance = null): void
    {
        $instance = $instance ?: $this->instanceName();
        $state = $this->connectionState($instance)['instance']['state'] ?? 'not_found';

        if ($state === 'not_found') {
            $this->createInstance($instance);
        }
    }

    public function createInstance(?string $instance = null): array
    {
        $instance = $instance ?: $this->instanceName();

        $response = $this->send(fn (PendingRequest $client) => $client->post('/instance/create', [
            'instanceName' => $instance,
            'integration' => 'WHATSAPP-BAILEYS',
            'qrcode' => true,
        ]));

        if ($response->status() === 403) {
            return $response->json() ?? [];
        }

        $this->throwOnFailure($response);

        return $response->json() ?? [];
    }

    /**
     * Ensures the instance exists and returns a fresh QR code payload.
     */
    public function connect(?string $instance = null): array
    {
        $instance = $instance ?: $this->instanceName();
        $this->ensureInstance($instance);

        $response = $this->send(fn (PendingRequest $client) => $client->get("/instance/connect/{$instance}"));
        $this->throwOnFailure($response);

        return $response->json() ?? [];
    }

    public function logout(?string $instance = null): void
    {
        $instance = $instance ?: $this->instanceName();

        $response = $this->send(fn (PendingRequest $client) => $client->delete("/instance/logout/{$instance}"));

        if ($response->status() === 404) {
            return;
        }

        $this->throwOnFailure($response);
    }

    public function deleteInstance(?string $instance = null): void
    {
        $instance = $instance ?: $this->instanceName();

        $response = $this->send(fn (PendingRequest $client) => $client->delete("/instance/delete/{$instance}"));

        if ($response->status() === 404) {
            return;
        }

        $this->throwOnFailure($response);
    }

    public function extractQrCode(array $payload): ?string
    {
        $candidates = [
            data_get($payload, 'base64'),
            data_get($payload, 'qrcode.base64'),
            data_get($payload, 'qrcode'),
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && $value !== '') {
                return str_starts_with($value, 'data:') ? $value : 'data:image/png;base64,'.$value;
            }
        }

        return null;
    }

    /**
     * Send a text message to a phone number or WhatsApp JID (including groups).
     *
     * Group JIDs look like `120363...@g.us` and are passed through unchanged.
     */
    public function sendText(string $phoneOrJid, string $text): array
    {
        $instance = $this->instanceName();
        $number = $this->normalizeRecipient($phoneOrJid);

        $response = $this->send(fn (PendingRequest $client) => $client->post("/message/sendText/{$instance}", [
            'number' => $number,
            'text' => $text,
        ]));

        $this->throwOnFailure($response);

        return $response->json() ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchAllGroups(?string $instance = null): array
    {
        $instance = $instance ?: $this->instanceName();

        $response = $this->send(fn (PendingRequest $client) => $client->get(
            "/group/fetchAllGroups/{$instance}",
            ['getParticipants' => 'false']
        ));

        $this->throwOnFailure($response);

        $json = $response->json();

        if (is_array($json) && array_is_list($json)) {
            return $json;
        }

        if (is_array($json) && isset($json['groups']) && is_array($json['groups'])) {
            return $json['groups'];
        }

        return is_array($json) ? array_values($json) : [];
    }

    protected function normalizeRecipient(string $phoneOrJid): string
    {
        $value = trim($phoneOrJid);

        if ($value === '') {
            throw new \RuntimeException('Geçersiz WhatsApp alıcısı.');
        }

        // Group or user JID — keep as-is (Evolution accepts full JIDs).
        if (str_contains($value, '@')) {
            return $value;
        }

        return $this->normalizeWhatsappNumber($value);
    }

    protected function normalizeWhatsappNumber(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if ($digits === '') {
            throw new \RuntimeException('Geçersiz telefon numarası.');
        }

        if (str_starts_with($digits, '0')) {
            return '90'.substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '90'.$digits;
        }

        return $digits;
    }

    protected function send(callable $callback): Response
    {
        try {
            return $callback($this->client());
        } catch (ConnectionException $e) {
            throw new \RuntimeException(
                'Evolution API\'ye bağlanılamadı. Docker container\'ının çalıştığından ve adresin doğru olduğundan emin olun.',
                previous: $e
            );
        }
    }

    protected function throwOnFailure(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('message')
            ?? $response->json('response.message')
            ?? $response->body();

        if (is_array($message)) {
            $message = collect($message)->flatten()->filter()->implode(' ');
        }

        throw new \RuntimeException('Evolution API hatası: '.$message);
    }
}
