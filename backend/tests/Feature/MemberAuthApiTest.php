<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberAuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_wrong_password_is_rejected(): void
    {
        Member::create(['username' => 'alice', 'password' => 'correct-password', 'is_active' => true]);

        $response = $this->postJson('/api/v1/login', [
            'username' => 'alice',
            'password' => 'wrong-password',
            'device_id' => 'device-1',
        ]);

        $response->assertStatus(401)->assertJson(['code' => 'invalid_credentials']);
        $this->assertDatabaseHas('login_logs', ['username' => 'alice', 'success' => false, 'reason' => 'invalid_credentials']);
    }

    public function test_first_login_binds_the_device(): void
    {
        $member = Member::create(['username' => 'bob', 'password' => 'secret123', 'is_active' => true]);

        $response = $this->postJson('/api/v1/login', [
            'username' => 'bob',
            'password' => 'secret123',
            'device_id' => 'device-A',
        ]);

        $response->assertOk()->assertJsonPath('data.member.username', 'bob');
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertSame('device-A', $member->refresh()->device_id);
    }

    public function test_login_from_a_different_device_is_rejected(): void
    {
        $member = Member::create(['username' => 'carol', 'password' => 'secret123', 'is_active' => true]);
        $member->forceFill(['device_id' => 'device-A', 'device_registered_at' => now()])->save();

        $response = $this->postJson('/api/v1/login', [
            'username' => 'carol',
            'password' => 'secret123',
            'device_id' => 'device-B',
        ]);

        $response->assertStatus(409)->assertJson(['code' => 'device_mismatch']);
    }

    public function test_login_from_the_registered_device_still_works(): void
    {
        $member = Member::create(['username' => 'dave', 'password' => 'secret123', 'is_active' => true]);
        $member->forceFill(['device_id' => 'device-A', 'device_registered_at' => now()])->save();

        $response = $this->postJson('/api/v1/login', [
            'username' => 'dave',
            'password' => 'secret123',
            'device_id' => 'device-A',
        ]);

        $response->assertOk();
    }

    public function test_inactive_member_cannot_log_in(): void
    {
        Member::create(['username' => 'erin', 'password' => 'secret123', 'is_active' => false]);

        $response = $this->postJson('/api/v1/login', [
            'username' => 'erin',
            'password' => 'secret123',
            'device_id' => 'device-A',
        ]);

        $response->assertStatus(403)->assertJson(['code' => 'inactive']);
    }

    public function test_expired_member_cannot_log_in(): void
    {
        Member::create([
            'username' => 'frank',
            'password' => 'secret123',
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'username' => 'frank',
            'password' => 'secret123',
            'device_id' => 'device-A',
        ]);

        $response->assertStatus(403)->assertJson(['code' => 'expired']);
    }

    public function test_me_returns_the_current_member_with_a_valid_token(): void
    {
        $member = Member::create(['username' => 'gina', 'password' => 'secret123', 'is_active' => true]);
        $token = $member->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")->getJson('/api/v1/me');

        $response->assertOk()->assertJsonPath('data.member.username', 'gina');
    }

    public function test_me_rejects_and_revokes_the_token_once_the_member_is_deactivated(): void
    {
        $member = Member::create(['username' => 'hank', 'password' => 'secret123', 'is_active' => true]);
        $token = $member->createToken('test')->plainTextToken;

        $member->update(['is_active' => false]);

        $response = $this->withHeader('Authorization', "Bearer $token")->getJson('/api/v1/me');
        $response->assertStatus(403)->assertJson(['code' => 'inactive']);

        // The token must be gone from the DB so it can never authenticate again,
        // even outside of this same guard-cached request/response cycle.
        $this->assertSame(0, $member->tokens()->count());
    }

    public function test_me_without_a_token_is_unauthorized(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_a_new_login_revokes_the_previous_token(): void
    {
        $member = Member::create(['username' => 'ivan', 'password' => 'secret123', 'is_active' => true]);

        $firstToken = $this->postJson('/api/v1/login', [
            'username' => 'ivan',
            'password' => 'secret123',
            'device_id' => 'device-A',
        ])->json('data.token');

        $this->postJson('/api/v1/login', [
            'username' => 'ivan',
            'password' => 'secret123',
            'device_id' => 'device-A',
        ])->assertOk();

        $this->withHeader('Authorization', "Bearer $firstToken")->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_logout_revokes_the_token(): void
    {
        $member = Member::create(['username' => 'julia', 'password' => 'secret123', 'is_active' => true]);
        $token = $member->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")->postJson('/api/v1/logout')->assertOk();

        $this->assertSame(0, $member->tokens()->count());
    }

    public function test_logout_marks_member_offline(): void
    {
        $member = Member::create(['username' => 'halilg', 'password' => 'secret123', 'is_active' => true]);
        $token = $member->createToken('test')->plainTextToken;
        $member->markOnline('127.0.0.1');

        $this->assertTrue($member->refresh()->is_online);

        $this->withHeader('Authorization', "Bearer $token")->postJson('/api/v1/logout')->assertOk();

        $this->assertFalse($member->refresh()->is_online);
        $this->assertFalse($member->isCurrentlyOnline());
    }

    public function test_heartbeat_after_token_revoke_does_not_mark_online(): void
    {
        $member = Member::create(['username' => 'stale', 'password' => 'secret123', 'is_active' => true]);
        $token = $member->createToken('test')->plainTextToken;
        $member->markOnline('127.0.0.1');

        $this->withHeader('Authorization', "Bearer $token")->postJson('/api/v1/logout')->assertOk();
        $this->assertFalse($member->refresh()->is_online);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/heartbeat')
            ->assertStatus(401);

        $this->assertFalse($member->refresh()->is_online);
    }

    public function test_stale_heartbeat_is_not_currently_online(): void
    {
        $member = Member::create(['username' => 'ghost', 'password' => 'secret123', 'is_active' => true]);
        $member->forceFill([
            'is_online' => true,
            'last_heartbeat_at' => now()->subMinutes(Member::HEARTBEAT_TIMEOUT_MINUTES + 1),
        ])->save();

        $this->assertFalse($member->isCurrentlyOnline());
        $this->assertSame(0, Member::query()->currentlyOnline()->count());
    }

    public function test_admin_can_reset_a_members_device_lock(): void
    {
        $member = Member::create(['username' => 'karen', 'password' => 'secret123', 'is_active' => true]);
        $member->forceFill(['device_id' => 'device-A', 'device_registered_at' => now()])->save();
        $member->createToken('test');

        $member->resetDevice();

        $this->assertNull($member->refresh()->device_id);
        $this->assertSame(0, $member->tokens()->count());

        $this->postJson('/api/v1/login', [
            'username' => 'karen',
            'password' => 'secret123',
            'device_id' => 'device-B',
        ])->assertOk();
    }
}
