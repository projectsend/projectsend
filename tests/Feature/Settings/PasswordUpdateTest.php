<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings/password')
            ->put('/settings/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/password');

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    /**
     * Changing a password is how someone reacts to a session they believe is
     * stolen, so it has to actually end that session. AuthenticateSession
     * binds each session to the password hash it was created under; the
     * session doing the change is re-stamped, every other one fails its next
     * request.
     */
    public function test_changing_the_password_invalidates_the_accounts_other_sessions()
    {
        $user = User::factory()->create();
        $oldHash = $user->password;

        $this->actingAs($user)
            ->from('/settings/password')
            ->put('/settings/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertSessionHasNoErrors();

        // The session that made the change carries the new hash and stays in.
        $this->assertNotSame($oldHash, session('password_hash_web'));
        $this->actingAs($user)->get('/settings/password')->assertOk();

        // Another session, still holding the pre-change hash, is turned away.
        $this->flushSession();
        $this->withSession(['password_hash_web' => $oldHash]);
        $this->actingAs($user)->get('/settings/password')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_correct_password_must_be_provided_to_update_password()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings/password')
            ->put('/settings/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/settings/password');
    }
}
