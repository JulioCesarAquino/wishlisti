<?php

namespace Tests\Feature\Identity;

use App\Filament\Resources\Events\Events\EventResource;
use App\Models\Events\Event;
use App\Models\Identity\InviteRequest;
use App\Models\User;
use App\Notifications\Identity\InviteRequestReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Hosts sign up on their own from the home page and go straight to create
 * their first event (RN-01).
 */
class HostSignUpTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function data(array $overrides = []): array
    {
        return [
            'name' => 'Ana Souza',
            'email' => 'Ana@Exemplo.com ',
            'whatsapp' => '(11) 98888-7777',
            'password' => 'Senha@Forte1',
            'password_confirmation' => 'Senha@Forte1',
            ...$overrides,
        ];
    }

    public function test_the_page_opens(): void
    {
        $this->get('/cadastro')->assertOk()->assertInertia(fn ($page) => $page->component('cadastro'));
    }

    public function test_a_signed_in_user_goes_to_the_panel(): void
    {
        $this->actingAs(User::factory()->create())->get('/cadastro')->assertRedirect('/admin');
    }

    public function test_signing_up_creates_the_account_signs_in_and_opens_the_new_event_form(): void
    {
        Notification::fake();
        $this->withoutDefer();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->post('/cadastro', $this->data(), ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', EventResource::getUrl('create'));

        $user = User::query()->where('email', 'ana@exemplo.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->isAdmin());
        $this->assertTrue(password_verify('Senha@Forte1', $user->password));

        $signUp = InviteRequest::query()->sole();
        $this->assertTrue($signUp->via_signup);
        $this->assertSame(InviteRequest::STATUS_APPROVED, $signUp->status);
        $this->assertSame($user->id, $signUp->invited_user_id);
        $this->assertSame('(11) 98888-7777', $signUp->whatsapp);

        Notification::assertSentTo($admin, InviteRequestReceivedNotification::class);
    }

    public function test_the_campaign_and_the_event_page_that_brought_them_are_kept(): void
    {
        $event = Event::factory()->create(['slug' => 'festa-da-bia']);

        $this->post('/cadastro', $this->data([
            'ref' => 'festa-da-bia',
            'utm_source' => 'instagram',
            'utm_medium' => 'paid',
            'utm_campaign' => 'casamentos-nov',
        ]));

        $signUp = InviteRequest::query()->sole();
        $this->assertSame($event->id, $signUp->referred_by_event_id);
        $this->assertSame('instagram', $signUp->utm_source);
        $this->assertSame('paid', $signUp->utm_medium);
        $this->assertSame('casamentos-nov', $signUp->utm_campaign);
    }

    public function test_an_email_already_in_use_is_refused(): void
    {
        User::factory()->create(['email' => 'ana@exemplo.com']);

        $this->post('/cadastro', $this->data())->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, InviteRequest::query()->count());
    }

    public function test_a_weak_password_is_refused(): void
    {
        $this->post('/cadastro', $this->data(['password' => 'fraca', 'password_confirmation' => 'fraca']))
            ->assertSessionHasErrors('password');

        $this->assertSame(0, User::query()->count());
    }

    public function test_the_confirmation_must_match(): void
    {
        $this->post('/cadastro', $this->data(['password_confirmation' => 'Outra@Senha1']))
            ->assertSessionHasErrors('password');
    }

    public function test_bots_filling_the_hidden_field_are_refused(): void
    {
        $this->post('/cadastro', $this->data(['website' => 'http://spam.example']))
            ->assertSessionHasErrors('website');

        $this->assertSame(0, User::query()->count());
    }

    public function test_the_site_addresses_are_not_taken_by_events(): void
    {
        $event = Event::factory()->create(['title' => 'Cadastro', 'slug' => null]);

        $this->assertSame('cadastro-2', $event->slug);
    }
}
