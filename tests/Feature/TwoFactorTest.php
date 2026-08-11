<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledUser(?string $secret = null): array
    {
        $secret ??= (new Google2FA)->generateSecretKey(32);

        $user = User::factory()->create(['password' => 'secret-password']);
        $user->forceFill([
            'totp_secret' => $secret,
            'totp_confirmed_at' => now(),
            'recovery_codes' => ['AAAAA-BBBBB', 'CCCCC-DDDDD'],
        ])->save();

        return [$user, $secret];
    }

    public function test_setup_confirms_with_valid_code_and_generates_recovery_codes(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/two-factor/setup');
        $response->assertOk()->assertSee('Set up two-factor authentication');

        $secret = session('two_factor_setup.secret');
        $code = (new Google2FA)->getCurrentOtp($secret);

        $this->actingAs($user)
            ->post('/two-factor/setup', ['code' => $code])
            ->assertRedirect('/two-factor/recovery-codes');

        $user->refresh();
        $this->assertTrue($user->hasConfirmedTwoFactor());
        $this->assertSame($secret, $user->totp_secret);
        $this->assertCount(8, $user->recovery_codes);
    }

    public function test_setup_rejects_an_invalid_code(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/two-factor/setup');

        $this->actingAs($user)
            ->post('/two-factor/setup', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->refresh()->hasConfirmedTwoFactor());
    }

    public function test_enrolled_login_requires_totp_challenge(): void
    {
        [$user, $secret] = $this->enrolledUser();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertRedirect('/two-factor/challenge');

        $this->assertGuest();

        $code = (new Google2FA)->getCurrentOtp($secret);

        $this->post('/two-factor/challenge', ['code' => $code])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_challenge_rejects_an_invalid_code(): void
    {
        [$user] = $this->enrolledUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password']);

        $this->post('/two-factor/challenge', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_totp_code_cannot_be_replayed(): void
    {
        [$user, $secret] = $this->enrolledUser();
        $code = (new Google2FA)->getCurrentOtp($secret);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password']);
        $this->post('/two-factor/challenge', ['code' => $code])->assertRedirect('/dashboard');

        $this->post('/logout');

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password']);
        $this->post('/two-factor/challenge', ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_recovery_code_signs_in_once(): void
    {
        [$user] = $this->enrolledUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password']);

        $this->post('/two-factor/challenge', ['recovery_code' => 'aaaaa-bbbbb'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(['CCCCC-DDDDD'], $user->refresh()->recovery_codes);

        $this->post('/logout');

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password']);
        $this->post('/two-factor/challenge', ['recovery_code' => 'AAAAA-BBBBB'])
            ->assertSessionHasErrors('code');
    }

    public function test_regenerating_recovery_codes_replaces_the_set(): void
    {
        [$user] = $this->enrolledUser();

        $this->actingAs($user)
            ->post('/two-factor/recovery-codes')
            ->assertRedirect('/two-factor/recovery-codes');

        $codes = $user->refresh()->recovery_codes;
        $this->assertCount(8, $codes);
        $this->assertNotContains('AAAAA-BBBBB', $codes);
    }

    public function test_recovery_codes_download_returns_plain_text(): void
    {
        [$user] = $this->enrolledUser();

        $this->actingAs($user)
            ->get('/two-factor/recovery-codes/download')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('AAAAA-BBBBB');
    }

    public function test_challenge_page_requires_a_pending_login(): void
    {
        $this->get('/two-factor/challenge')->assertRedirect('/login');
        $this->post('/two-factor/challenge', ['code' => '123456'])->assertRedirect('/login');
    }
}
