<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Members\Pages\CreateMember;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Smoke tests that actually render the Filament pages/forms/tables through
 * Livewire, catching schema/table configuration mistakes that plain PHP
 * syntax checks can't (wrong component method, bad closure signature, etc).
 */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_members_list_page_renders_with_a_record(): void
    {
        $member = Member::create(['username' => 'renderme', 'password' => 'secret123', 'is_active' => true]);
        $member->forceFill(['device_id' => 'device-A'])->save();

        $this->actingAs($this->admin())
            ->get('/admin/members')
            ->assertSuccessful()
            ->assertSee('renderme');
    }

    public function test_member_create_page_renders(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/members/create')
            ->assertSuccessful();
    }

    public function test_member_edit_page_renders(): void
    {
        $member = Member::create(['username' => 'editme', 'password' => 'secret123', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->get("/admin/members/{$member->id}/edit")
            ->assertSuccessful();
    }

    public function test_admin_can_create_a_member_from_the_panel(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateMember::class)
            ->fillForm([
                'username' => 'newmember',
                'password' => 'secret123',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('members', ['username' => 'newmember']);
    }

    public function test_login_logs_list_page_renders(): void
    {
        \App\Models\LoginLog::create([
            'username' => 'someone',
            'device_id' => 'device-A',
            'success' => false,
            'reason' => 'invalid_credentials',
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/login-logs')
            ->assertSuccessful()
            ->assertSee('someone');
    }
}
