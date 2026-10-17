<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SubdirectoryRedirectTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'http://localhost/painel/public';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withServerVariables([
            'SCRIPT_NAME' => '/painel/public/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
            'PHP_SELF' => '/painel/public/index.php',
        ]);
    }

    public function test_login_preserves_the_application_base_path(): void
    {
        $user = User::factory()->create();
        $this->post(self::BASE.'/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(self::BASE.'/dashboard');
    }

    public function test_registration_redirect_and_verification_notice_preserve_the_base_path(): void
    {
        Notification::fake();
        $response = $this->post(self::BASE.'/register', ['name' => 'Teste', 'email' => 'subdirectory@example.test',
            'password' => 'password', 'password_confirmation' => 'password']);
        $response->assertRedirect(self::BASE.'/verify-email');
        $this->get($response->headers->get('Location'))->assertOk();
        $this->get(self::BASE.'/verify-email')->assertOk();
    }

    public function test_verified_email_and_confirmation_fallbacks_keep_the_base_path(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(self::BASE.'/verify-email')->assertRedirect(self::BASE.'/dashboard');
        $this->post(self::BASE.'/email/verification-notification')->assertRedirect(self::BASE.'/dashboard');
        $this->post(self::BASE.'/confirm-password', ['password' => 'password'])->assertRedirect(self::BASE.'/dashboard');
    }

    public function test_logout_returns_to_the_application_home(): void
    {
        $this->actingAs(User::factory()->create())->post(self::BASE.'/logout')->assertRedirect(self::BASE);
        $this->assertGuest();
    }

    public function test_profile_deletion_returns_to_the_application_home(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->delete(self::BASE.'/profile', ['password' => 'password'])->assertRedirect(self::BASE);
        $this->assertGuest();
        $this->assertModelMissing($user);
    }

    public function test_guest_redirect_and_existing_intended_account_link_keep_the_base_path(): void
    {
        $user = User::factory()->create();
        $this->get(self::BASE.'/profile')->assertRedirect(self::BASE.'/login');
        $this->post(self::BASE.'/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(self::BASE.'/profile');
        $this->get(self::BASE.'/profile')->assertOk();
        $this->get(self::BASE.'/login')->assertRedirect(self::BASE.'/dashboard');
    }

    public function test_account_list_and_signed_email_verification_keep_the_base_path(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get(self::BASE.'/verify-email')->assertOk();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30),
            ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect(self::BASE.'/dashboard?verified=1');
        $this->get(self::BASE.'/contas?days=7')->assertRedirect(self::BASE.'/dashboard?days=7');
        $this->get(self::BASE.'/dashboard?days=7')->assertOk();
    }
}
