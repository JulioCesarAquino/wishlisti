<?php

namespace Tests\Feature\Logging;

use App\Logging\CreateTelegramLogger;
use App\Logging\OncePerWindowHandler;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Monolog\Handler\NullHandler;
use Monolog\Handler\TestHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Level;
use Monolog\Logger;
use Tests\TestCase;

class LogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_admin_opens_the_log_screen(): void
    {
        $this->get('/admin/logs')->assertForbidden();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin/logs')
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/logs')
            ->assertOk();
    }

    public function test_log_files_cannot_be_deleted_from_the_screen(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertTrue($admin->can('viewLogViewer'));
        $this->assertFalse($admin->can('deleteLogFile', [null]));
    }

    public function test_the_panel_menu_links_to_the_logs_for_the_admin_only(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin')
            ->assertSee('Logs do sistema');

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin')
            ->assertDontSee('Logs do sistema');
    }

    public function test_without_a_bot_the_telegram_channel_does_nothing(): void
    {
        $logger = (new CreateTelegramLogger)(['token' => null, 'chat_id' => null]);

        $this->assertInstanceOf(NullHandler::class, $logger->getHandlers()[0]);
    }

    public function test_with_a_bot_failures_to_reach_telegram_never_break_the_app(): void
    {
        $logger = (new CreateTelegramLogger)(['token' => '123:abc', 'chat_id' => '42']);

        $this->assertInstanceOf(WhatFailureGroupHandler::class, $logger->getHandlers()[0]);
    }

    public function test_a_repeated_error_alerts_once_per_window(): void
    {
        Carbon::setTestNow('2026-10-02 12:00:00');
        $sent = new TestHandler(Level::Error);
        $logger = new Logger('test', [new OncePerWindowHandler($sent, minutes: 10)]);

        $logger->error('Mercado Pago: falha ao criar pagamento via Brick');
        $logger->error('Mercado Pago: falha ao criar pagamento via Brick');
        $logger->error('Outro erro');
        $logger->warning('Só um aviso');

        $this->assertCount(2, $sent->getRecords());

        Carbon::setTestNow('2026-10-02 12:11:00');
        $logger->error('Mercado Pago: falha ao criar pagamento via Brick');

        $this->assertCount(3, $sent->getRecords());
    }
}
