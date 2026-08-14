<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The app redirects everything to /setup until a staff user exists.
        User::factory()->create();
    }

    public function test_reset_password_link_screen_can_be_rendered()
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_reset_password_screen_can_be_rendered()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password-1234',
                'password_confirmation' => 'new-password-1234',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    /**
     * That this endpoint enforces the policy at all — the shipped default
     * being a 12-character minimum.
     *
     * This assertion used to be described as covering the policy itself,
     * on the grounds that Password::defaults() is central and every field
     * leans on it. That stopped being true when the minimum became
     * configurable: PasswordPolicy now decides it, and whether a *changed*
     * minimum reaches each surface is proven in
     * tests/Feature/Identity/PasswordPolicyTest.php.
     */
    public function test_a_password_below_the_minimum_length_is_rejected()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'short-11ch',
                'password_confirmation' => 'short-11ch',
            ])->assertSessionHasErrors('password');

            return true;
        });
    }
}
