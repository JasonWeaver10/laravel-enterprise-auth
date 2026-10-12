<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * Covers the decisions in the callback rather than the OIDC handshake itself.
 *
 * The handshake is Socialite's and Okta's business and is already tested by
 * both. What is worth pinning down here is what this application does with a
 * valid assertion, because that is where the choices are.
 */
class OktaAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.okta', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'redirect' => 'http://localhost:8000/auth/okta/callback',
            'base_url' => 'https://dev-00000000.okta.com',
            'auth_server_id' => 'default',
            'jit_provisioning' => false,
        ]);
    }

    /**
     * Stands in for a completed round trip to Okta.
     */
    private function fakeOktaUser(string $sub, string $email, string $name = 'Test Person'): void
    {
        $socialiteUser = (new SocialiteUser)->map([
            'id' => $sub,
            'name' => $name,
            'email' => $email,
            'nickname' => null,
        ]);

        $socialiteUser->user = [
            'sub' => $sub,
            'name' => $name,
            'email' => $email,
            'groups' => ['Everyone'],
        ];

        Socialite::shouldReceive('driver')->with('okta')->andReturn(
            Mockery::mock(Provider::class, function ($mock) use ($socialiteUser) {
                $mock->shouldReceive('user')->andReturn($socialiteUser);
            })
        );
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_the_root_route_lands_a_guest_on_login(): void
    {
        $this->get('/')
            ->assertRedirect('/dashboard')
            ->assertSessionMissing('okta_claims');
    }

    public function test_the_login_page_offers_okta_when_credentials_are_present(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in with Okta');
    }

    public function test_the_login_page_says_so_when_it_is_not_configured(): void
    {
        config()->set('services.okta.client_id', null);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Not configured yet')
            ->assertDontSee('Sign in with Okta');
    }

    public function test_a_verified_stranger_is_refused_when_jit_provisioning_is_off(): void
    {
        $this->fakeOktaUser('00uSTRANGER', 'stranger@example.com');

        $this->get('/auth/okta/callback')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertStringContainsString(
            'no account for you here yet',
            session('status'),
        );
    }

    public function test_a_verified_stranger_is_created_when_jit_provisioning_is_on(): void
    {
        config()->set('services.okta.jit_provisioning', true);
        $this->fakeOktaUser('00uNEWCOMER', 'newcomer@example.com', 'New Comer');

        $this->get('/auth/okta/callback')->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'okta_id' => '00uNEWCOMER',
            'email' => 'newcomer@example.com',
            'name' => 'New Comer',
        ]);

        // A federated account has no password, and should not be given a
        // placeholder hash that later reads as a credential.
        $this->assertNull(User::firstWhere('email', 'newcomer@example.com')->password);
    }

    public function test_an_account_that_already_exists_is_linked_by_email_on_first_sign_in(): void
    {
        $user = User::factory()->create([
            'email' => 'provisioned@example.com',
            'okta_id' => null,
        ]);

        $this->fakeOktaUser('00uPROVISIONED', 'provisioned@example.com');

        $this->get('/auth/okta/callback')->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame('00uPROVISIONED', $user->fresh()->okta_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_a_returning_user_is_matched_on_subject_even_after_their_email_changes(): void
    {
        $user = User::factory()->create([
            'email' => 'maiden.name@example.com',
            'okta_id' => '00uSTABLE',
        ]);

        // Okta now asserts a different address for the same person. Matching on
        // email here would create a second account and orphan the first.
        $this->fakeOktaUser('00uSTABLE', 'married.name@example.com');

        $this->get('/auth/okta/callback')->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_the_claims_from_the_sign_in_are_kept_in_the_session(): void
    {
        config()->set('services.okta.jit_provisioning', true);
        $this->fakeOktaUser('00uCLAIMS', 'claims@example.com');

        $this->get('/auth/okta/callback');

        $this->assertSame(['Everyone'], session('okta_claims.groups'));
    }

    public function test_signing_out_ends_the_local_session(): void
    {
        $user = User::factory()->create(['okta_id' => '00uBYE']);

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }
}
