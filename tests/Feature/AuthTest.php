<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'plan'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'plan' => 'free']);
    }

    public function test_register_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Test',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()->assertJsonStructure(['user', 'token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct')]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ])->assertUnprocessable();
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonFragment(['email' => $user->email]);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/logout')
            ->assertOk();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_google_callback_creates_new_user(): void
    {
        $socialUser = Mockery::mock('Laravel\Socialite\Two\User');
        $socialUser->shouldReceive('getId')->andReturn('google-uid-123');
        $socialUser->shouldReceive('getEmail')->andReturn('new@gmail.com');
        $socialUser->shouldReceive('getName')->andReturn('Google User');

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'new@gmail.com', 'google_id' => 'google-uid-123']);
        $this->assertStringContainsString('/auth/callback?token=', $response->headers->get('Location'));
    }

    public function test_google_callback_links_existing_email_user(): void
    {
        $user = User::factory()->create(['email' => 'existing@gmail.com', 'google_id' => null]);

        $socialUser = Mockery::mock('Laravel\Socialite\Two\User');
        $socialUser->shouldReceive('getId')->andReturn('google-uid-456');
        $socialUser->shouldReceive('getEmail')->andReturn('existing@gmail.com');
        $socialUser->shouldReceive('getName')->andReturn('Existing User');

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google/callback')->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'google_id' => 'google-uid-456']);
        $this->assertCount(1, User::where('email', 'existing@gmail.com')->get());
    }

    public function test_google_callback_finds_user_by_google_id(): void
    {
        $user = User::factory()->create(['google_id' => 'google-uid-789']);

        $socialUser = Mockery::mock('Laravel\Socialite\Two\User');
        $socialUser->shouldReceive('getId')->andReturn('google-uid-789');
        $socialUser->shouldReceive('getEmail')->andReturn($user->email);
        $socialUser->shouldReceive('getName')->andReturn($user->name);

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect();
        $this->assertStringContainsString('/auth/callback?token=', $response->headers->get('Location'));
        $this->assertCount(1, User::where('google_id', 'google-uid-789')->get());
    }

    public function test_google_callback_redirects_on_error(): void
    {
        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andThrow(new \Exception('OAuth error'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google/callback')
            ->assertRedirect()
            ->assertRedirectContains('/login?error=google_failed');
    }
}
