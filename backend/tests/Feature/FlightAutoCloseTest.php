<?php

namespace Tests\Feature;

use App\Models\FccSession;
use App\Models\Member;
use App\Services\FlightAutoCloseService;
use App\Services\FlightGroupNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class FlightAutoCloseTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_offline_auto_closes_open_flight_and_notifies(): void
    {
        $member = Member::create([
            'username' => 'pilot1',
            'password' => 'secret123',
            'is_active' => true,
            'name' => 'Pilot One',
        ]);
        $member->forceFill([
            'is_online' => true,
            'last_heartbeat_at' => now()->subMinutes(Member::HEARTBEAT_TIMEOUT_MINUTES + 1),
        ])->save();

        $start = FccSession::create([
            'member_id' => $member->id,
            'action' => 'fcc_enable',
            'success' => true,
            'aircraft_serial' => '1581FTESTSERIAL',
            'device_model' => 'DJI Mini 4 Pro',
            'province' => 'Şanlıurfa',
            'district' => 'Haliliye',
            'neighborhood' => 'Merkez',
        ]);
        $start->forceFill([
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ])->save();

        $notifier = Mockery::mock(FlightGroupNotificationService::class);
        $notifier->shouldReceive('notifySession')
            ->once()
            ->withArgs(function (Member $m, FccSession $session, array $location) use ($member) {
                return $m->id === $member->id
                    && $session->action === 'fcc_disable'
                    && $session->success === true
                    && $session->failure_reason === FlightAutoCloseService::REASON_AUTO_CLOSED_OFFLINE
                    && ($session->duration_seconds ?? 0) > 0;
            });
        $this->app->instance(FlightGroupNotificationService::class, $notifier);

        $this->artisan('members:check-offline')->assertSuccessful();

        $this->assertFalse($member->refresh()->is_online);
        $this->assertDatabaseHas('fcc_sessions', [
            'member_id' => $member->id,
            'action' => 'fcc_disable',
            'success' => 1,
            'failure_reason' => FlightAutoCloseService::REASON_AUTO_CLOSED_OFFLINE,
        ]);
    }

    public function test_check_offline_does_not_close_when_flight_already_ended(): void
    {
        $member = Member::create([
            'username' => 'pilot2',
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $member->forceFill([
            'is_online' => true,
            'last_heartbeat_at' => now()->subMinutes(Member::HEARTBEAT_TIMEOUT_MINUTES + 1),
        ])->save();

        $enable = FccSession::create([
            'member_id' => $member->id,
            'action' => 'fcc_enable',
            'success' => true,
        ]);
        $enable->forceFill([
            'created_at' => now()->subMinutes(40),
            'updated_at' => now()->subMinutes(40),
        ])->save();

        $disable = FccSession::create([
            'member_id' => $member->id,
            'action' => 'fcc_disable',
            'success' => true,
            'duration_seconds' => 600,
        ]);
        $disable->forceFill([
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ])->save();

        $notifier = Mockery::mock(FlightGroupNotificationService::class);
        $notifier->shouldNotReceive('notifySession');
        $this->app->instance(FlightGroupNotificationService::class, $notifier);

        $this->artisan('members:check-offline')->assertSuccessful();

        $this->assertSame(1, FccSession::query()
            ->where('member_id', $member->id)
            ->where('action', 'fcc_disable')
            ->count());
    }

    public function test_auto_close_end_message_mentions_connection_loss(): void
    {
        config([
            'services.evolution.url' => 'http://evolution.test',
            'services.evolution.key' => 'test-key',
            'services.evolution.instance' => 'freefcc',
            'services.evolution.flight_notify_to' => '905551234567',
        ]);

        Http::fake([
            'evolution.test/*' => Http::response(['key' => ['id' => 'msg-1']], 200),
        ]);

        $member = Member::create([
            'username' => 'pilot3',
            'password' => 'secret123',
            'is_active' => true,
            'name' => 'Pilot Three',
        ]);

        $start = FccSession::create([
            'member_id' => $member->id,
            'action' => 'auto_fcc',
            'success' => true,
            'device_model' => 'DJI Air 3',
        ]);
        $start->forceFill([
            'created_at' => now()->subMinutes(15),
            'updated_at' => now()->subMinutes(15),
        ])->save();

        $session = app(FlightAutoCloseService::class)->closeOpenFlightIfNeeded($member);

        $this->assertNotNull($session);
        $this->assertSame(FlightAutoCloseService::REASON_AUTO_CLOSED_OFFLINE, $session->failure_reason);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/message/sendText/')) {
                return false;
            }

            $text = $request['text'] ?? '';

            return str_contains($text, 'Uçuş tamamlandı')
                && str_contains($text, 'Bağlantı kesildi (kumanda kapandı veya uygulama yanıt vermedi)');
        });
    }
}
