<?php

namespace Tests\Feature\Identity;

use App\Filament\Resources\Identity\InviteRequests\Pages\ListInviteRequests;
use App\Models\Events\Event;
use App\Models\Identity\InviteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every public page ends with "Feita com Wishlisti", a link to the home
 * page carrying the event's slug: the invite request keeps which event
 * brought the would-be host.
 */
class InviteRequestReferralTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function request(array $overrides = []): void
    {
        $this->post('/solicitar-convite', [
            'name' => 'Maria Convidada',
            'email' => 'maria@example.com',
            'whatsapp' => '5511999999999',
            ...$overrides,
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_the_request_keeps_the_event_page_that_brought_them(): void
    {
        $event = Event::factory()->create(['slug' => 'ana-e-pedro']);

        $this->request(['ref' => 'ana-e-pedro']);

        $this->assertSame($event->id, InviteRequest::sole()->referred_by_event_id);
    }

    public function test_without_a_ref_or_with_an_unknown_one_the_request_still_goes_through(): void
    {
        $this->request();
        $this->request(['email' => 'joao@example.com', 'ref' => 'nao-existe']);

        $this->assertSame(2, InviteRequest::whereNull('referred_by_event_id')->count());
    }

    public function test_the_ref_cannot_be_set_as_an_id_directly(): void
    {
        $event = Event::factory()->create();

        $this->request(['referred_by_event_id' => $event->id]);

        $this->assertNull(InviteRequest::sole()->referred_by_event_id);
    }

    public function test_the_request_survives_the_event_being_deleted(): void
    {
        $event = Event::factory()->create(['slug' => 'ana-e-pedro']);
        $this->request(['ref' => 'ana-e-pedro']);

        $event->forceDelete();

        $this->assertNull(InviteRequest::sole()->referred_by_event_id);
    }

    public function test_the_admin_sees_and_filters_where_requests_came_from(): void
    {
        $event = Event::factory()->create(['slug' => 'ana-e-pedro', 'title' => 'Casamento Ana e Pedro']);
        $referred = InviteRequest::factory()->create(['name' => 'Maria Indicada']);
        $referred->forceFill(['referred_by_event_id' => $event->id])->save();
        $direct = InviteRequest::factory()->create(['name' => 'João Direto']);

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(ListInviteRequests::class)
            ->assertSee('Casamento Ana e Pedro')
            ->filterTable('referred', true)
            ->assertCanSeeTableRecords([$referred])
            ->assertCanNotSeeTableRecords([$direct]);
    }
}
