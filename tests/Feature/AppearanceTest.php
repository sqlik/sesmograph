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

    public function test_theme_defaults_to_hum_with_the_top_navigation(): void
    {
        $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-theme="hum"', false)
            ->assertDontSee('aria-current="page"', false);
    }

    public function test_theme_can_be_switched_to_mono_and_renders_the_sidebar(): void
    {
        $user = $this->admin();

        $this->actingAs($user)
            ->from('/settings/appearance')
            ->put('/settings/appearance', ['theme' => 'mono'])
            ->assertRedirect('/settings/appearance');

        $this->assertSame('mono', $user->fresh()->theme);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-theme="mono"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Configure');
    }

    public function test_unknown_theme_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->put('/settings/appearance', ['theme' => 'disco'])
            ->assertSessionHasErrors('theme');
    }
}
