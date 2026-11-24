<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sign-in page (/entrar), in the home page's look; the panel's login
 * sends people there.
 */
class SignInTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'ana@exemplo.com',
            'password' => 'Senha@Forte1',
        ]);
    }

    public function test_the_page_opens_with_the_password_reset_link(): void
    {
        $this->get('/entrar')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('entrar')
                ->where('password_reset_url', fn ($url) => str_contains((string) $url, '/admin/password-reset')));
    }

    public function test_the_panels_login_sends_people_here(): void
    {
        $this->get('/admin/login')->assertRedirect('/entrar');
    }

    public function test_signing_in_goes_to_the_panel(): void
    {
        $this->post('/entrar', ['email' => ' Ana@Exemplo.com ', 'password' => 'Senha@Forte1'], ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', url('/admin'));

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_it_goes_back_to_the_page_that_asked_for_it(): void
    {
        $this->get('/admin/events')->assertRedirect('/admin/login');

        $this->post('/entrar', ['email' => 'ana@exemplo.com', 'password' => 'Senha@Forte1'], ['X-Inertia' => 'true'])
            ->assertHeader('X-Inertia-Location', url('/admin/events'));
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $this->post('/entrar', ['email' => 'ana@exemplo.com', 'password' => 'errada'])
            ->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);

        $this->assertGuest();
    }

    public function test_too_many_attempts_are_held_off(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post('/entrar', ['email' => 'ana@exemplo.com', 'password' => 'errada']);
        }

        $this->post('/entrar', ['email' => 'ana@exemplo.com', 'password' => 'Senha@Forte1'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_signed_in_user_goes_to_the_panel(): void
    {
        $this->actingAs($this->user)->get('/entrar')->assertRedirect('/admin');
    }
}
