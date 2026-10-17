<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_staff_can_list_users_and_moderators_cannot_mutate_them(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        $this->actingAs($user)->get('/admin/usuarios')->assertForbidden();
        $user->forceFill(['role' => 'mod'])->save();
        $this->get('/admin/usuarios')->assertOk();
        $this->patch('/admin/usuarios/'.$target->id.'/role', ['role' => 'mod'])->assertForbidden();
        $this->delete('/admin/usuarios/'.$target->id)->assertForbidden();
    }

    public function test_admin_changes_non_admin_roles_with_audit_and_protects_admins(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();
        $this->actingAs($admin)->get('/admin/usuarios')->assertOk();
        $this->patch('/admin/usuarios/'.$target->id.'/role', ['role' => 'mod'])->assertRedirect();
        $this->assertSame('mod', $target->fresh()->role);
        $this->assertDatabaseHas('audit_events', ['action' => 'user.role_changed', 'actor_id' => $admin->id, 'subject_id' => $target->id]);
        $this->patch('/admin/usuarios/'.$admin->id.'/role', ['role' => 'user'])->assertForbidden();
        $this->delete('/admin/usuarios/'.$admin->id)->assertForbidden();
        $other = User::factory()->create(['role' => 'admin']);
        $this->delete('/admin/usuarios/'.$other->id)->assertForbidden();
    }

    public function test_last_admin_cannot_delete_their_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->delete('/profile', ['password' => 'password'])->assertSessionHasErrors('account');
        $this->assertNotNull($admin->fresh());
        $this->assertAuthenticatedAs($admin);
    }

    public function test_console_promotion_requires_existing_user_and_is_audited(): void
    {
        $this->artisan('app:promote-admin missing@example.com')->assertFailed();
        $user = User::factory()->create();
        $this->artisan('app:promote-admin '.$user->email)->assertSuccessful();
        $this->assertSame('admin', $user->fresh()->role);
        $this->assertDatabaseHas('audit_events', ['action' => 'user.promoted_by_console', 'subject_id' => $user->id]);
    }

    public function test_registration_does_not_accept_admin_role(): void
    {
        $this->post('/register', ['name' => 'Tester', 'email' => 'safe@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'role' => 'admin']);
        $this->assertDatabaseHas('users', ['email' => 'safe@example.com', 'role' => 'user']);
    }
}
