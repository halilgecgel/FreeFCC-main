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
