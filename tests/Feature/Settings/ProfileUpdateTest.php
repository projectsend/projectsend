<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/settings/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/settings/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        // Deletion is soft: gone from every query, recoverable by an admin.
        $this->assertNull(User::query()->find($user->id));
        $this->assertNotNull(User::withTrashed()->find($user->id));
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings/profile')
            ->delete('/settings/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_erasure_grace_period_reflects_the_privacy_setting()
    {
        app(Settings::class)->set(Setting::AccountErasureGraceDays, 5);

        $user = User::factory()->create();

        // Deleting your account has its own screen; the profile form no
        // longer carries the block or the grace period behind it.
        $this->actingAs($user)
            ->get('/settings/delete-account')
            ->assertInertia(fn ($page) => $page
                ->component('settings/delete-account')
                ->where('erasureGraceDays', 5));

        $this->actingAs($user)->delete('/settings/profile', ['password' => 'password']);

        $this->assertTrue(
            User::withTrashed()->find($user->id)->erase_after->isSameDay(now()->addDays(5)),
        );
    }
}
