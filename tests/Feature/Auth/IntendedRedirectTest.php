<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsAccounts;
use Tests\TestCase;

class IntendedRedirectTest extends TestCase
{
    use BuildsAccounts, RefreshDatabase;

    public static function invalidDestinations(): array
    {
        return [
            'removed route' => ['http://localhost/route-removed'],
            'deleted account' => ['http://localhost/contas/999999'],
            'deleted account edit' => ['http://localhost/contas/999999/edit'],
            'forbidden administration' => ['http://localhost/admin/usuarios'],
            'login loop' => ['http://localhost/login'],
            'verification loop' => ['http://localhost/verify-email'],
            'JSON endpoint' => ['http://localhost/contas/1/sync'],
            'mutation endpoint' => ['http://localhost/contas/sync-all'],
            'external site' => ['https://example.test/dashboard'],
            'old server port' => ['http://localhost:9999/dashboard'],
            'protocol relative URL' => ['//example.test/dashboard'],
        ];
    }

    #[DataProvider('invalidDestinations')]
    public function test_invalid_or_inaccessible_intended_destinations_fall_back_to_dashboard(string $target): void
    {
        $user = User::factory()->create();
        $this->withSession(['url.intended' => $target])->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard')->assertSessionMissing('url.intended');
        $this->get('/dashboard')->assertOk();
    }

    public function test_account_from_another_user_is_not_a_login_destination(): void
    {
        $other = $this->account();
        $user = User::factory()->create();
        $this->withSession(['url.intended' => route('contas.show', $other)])
            ->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
    }

    public function test_owned_account_page_and_filters_are_preserved(): void
    {
        $account = $this->account();
        $target = route('contas.show', ['conta' => $account->id, 'tab' => 'history', 'days' => 7]);
        $this->withSession(['url.intended' => $target])
            ->post('/login', ['email' => $account->user->email, 'password' => 'password'])->assertRedirect($target);
        $this->get($target)->assertOk();
    }

    public function test_unverified_login_goes_to_notice_and_preserves_a_valid_destination_until_verification(): void
    {
        $user = User::factory()->unverified()->create();
        $account = $this->account($user);
        $target = route('contas.show', $account);
        $this->withSession(['url.intended' => $target])
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/verify-email')->assertSessionHas('url.intended', $target);
        $user->markEmailAsVerified();
        $this->actingAs($user->fresh())->get('/verify-email')->assertRedirect($target)->assertSessionMissing('url.intended');
    }

    public function test_new_registration_discards_stale_destination_from_another_session_identity(): void
    {
        Notification::fake();
        $this->withSession(['url.intended' => 'http://localhost/contas/999999'])->post('/register', [
            'name' => 'Novo usuário', 'email' => 'new@example.test', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertRedirect('/verify-email')->assertSessionMissing('url.intended');
        $this->get('/verify-email')->assertOk();
    }

    public function test_verification_link_does_not_redirect_to_a_deleted_account(): void
    {
        $user = User::factory()->unverified()->create();
        $signedUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30),
            ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->actingAs($user)->withSession(['url.intended' => 'http://localhost/contas/999999'])
            ->get($signedUrl)->assertRedirect('/dashboard?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_confirmation_and_verification_resend_discard_deleted_targets(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['url.intended' => 'http://localhost/contas/999999'])
            ->post('/confirm-password', ['password' => 'password'])->assertRedirect('/dashboard');
        $this->withSession(['url.intended' => 'http://localhost/old-page'])
            ->post('/email/verification-notification')->assertRedirect('/dashboard');
    }
}
