<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['totp_confirmed_at' => now()]);
    }

    public function test_theme_defaults_to_mono_with_the_sidebar(): void
    {
        $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-theme="mono"', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_theme_can_be_switched_to_hum_and_renders_the_top_navigation(): void
    {
        $user = $this->admin();

        $this->actingAs($user)
            ->from('/settings/appearance')
            ->put('/settings/appearance', ['theme' => 'hum'])
            ->assertRedirect('/settings/appearance');

        $this->assertSame('hum', $user->fresh()->theme);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-theme="hum"', false)
            ->assertDontSee('aria-current="page"', false);
    }

    public function test_unknown_theme_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->put('/settings/appearance', ['theme' => 'disco'])
            ->assertSessionHasErrors('theme');
    }

    public function test_theme_update_stores_the_plain_theme_cookie(): void
    {
        $this->actingAs($this->admin())
            ->put('/settings/appearance', ['theme' => 'mono'])
            ->assertPlainCookie('theme', 'mono');
    }

    public function test_login_page_follows_the_theme_cookie(): void
    {
        $this->withUnencryptedCookie('theme', 'mono')
            ->get('/login')
            ->assertOk()
            ->assertSee('data-theme="mono"', false);
    }

    public function test_login_page_ignores_an_unknown_theme_cookie(): void
    {
        $this->withUnencryptedCookie('theme', 'disco')
            ->get('/login')
            ->assertOk()
            ->assertSee('data-theme="mono"', false);
    }

    public function test_login_page_follows_a_hum_theme_cookie(): void
    {
        $this->withUnencryptedCookie('theme', 'hum')
            ->get('/login')
            ->assertOk()
            ->assertSee('data-theme="hum"', false);
    }

    public function test_logout_keeps_the_account_theme_via_the_cookie(): void
    {
        $user = $this->admin();
        $user->update(['theme' => 'mono']);

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login')
            ->assertPlainCookie('theme', 'mono');
    }
}
