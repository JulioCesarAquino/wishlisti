<?php

namespace Tests\Feature\Events;

use App\Models\Events\Event;
use App\Models\Events\EventAppearance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class EventActivityLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_an_event_logs_who_did_it_and_what_changed(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $host->id, 'title' => 'Título Antigo']);

        $this->actingAs($host);

        $event->update(['title' => 'Título Novo']);

        $activity = Activity::where('subject_type', Event::class)
            ->where('subject_id', $event->id)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($host->id, $activity->causer_id);
        $this->assertSame('atualizou o evento', $activity->description);
        $this->assertSame('Título Novo', $activity->attribute_changes['attributes']['title']);
        $this->assertSame('Título Antigo', $activity->attribute_changes['old']['title']);
    }

    public function test_mercado_pago_credentials_are_never_logged(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $host->id]);

        $this->actingAs($host);

        $event->paymentSettings()->create([
            'mp_access_token' => 'TEST-super-secret-token',
            'mp_public_key' => 'TEST-public-key',
        ]);

        $this->assertFalse(Activity::query()
            ->where('attribute_changes', 'like', '%TEST-super-secret-token%')
            ->exists());

        $activity = Activity::where('subject_type', Event::class)
            ->where('subject_id', $event->id)
            ->where('event', 'updated')
            ->latest()
            ->first();

        // Credentials live in their own, unlogged table, so saving them
        // records no activity on the event at all.
        $this->assertNull($activity);
    }

    public function test_appearance_changes_are_logged(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $host->id]);

        $this->actingAs($host);

        $event->appearance()->create(['primary_color' => '#ffffff']);

        $activity = Activity::where('subject_type', EventAppearance::class)->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame('atualizou a aparência do evento', $activity->description);
        $this->assertSame('#ffffff', $activity->attribute_changes['attributes']['primary_color']);
    }

    public function test_creating_an_event_is_logged(): void
    {
        $host = User::factory()->create(['is_admin' => false]);

        $this->actingAs($host);

        $event = Event::factory()->create(['user_id' => $host->id]);

        $activity = Activity::where('subject_type', Event::class)
            ->where('subject_id', $event->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('criou o evento', $activity->description);
    }
}
