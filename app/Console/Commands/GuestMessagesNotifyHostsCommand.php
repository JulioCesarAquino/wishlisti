<?php

namespace App\Console\Commands;

use App\Models\Guests\GuestMessage;
use App\Notifications\Events\GuestMessagesDigestNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Once an hour (routes/console.php): an e-mail to each host with the new
 * messages waiting for their approval — one per event, each message in a
 * single e-mail. Those approved in the meantime were already seen.
 */
#[Signature('messages:notify-hosts')]
#[Description('Envia aos anfitriões, por e-mail, os recados novos aguardando aprovação')]
class GuestMessagesNotifyHostsCommand extends Command
{
    public function handle(): int
    {
        $emails = 0;

        GuestMessage::query()
            ->whereNull('approved_at')
            ->whereNull('hosts_notified_at')
            // Not those of an event in the trash.
            ->whereHas('event')
            ->with(['event.user', 'event.coHosts'])
            ->orderBy('id')
            ->get()
            ->groupBy('event_id')
            ->each(function (Collection $messages) use (&$emails): void {
                $event = $messages->first()->event;

                foreach ($event->hosts() as $host) {
                    try {
                        $host->notify(new GuestMessagesDigestNotification($event, $messages));
                        $emails++;
                    } catch (Throwable $exception) {
                        Log::warning('Could not e-mail a host about new messages.', [
                            'event_id' => $event->id,
                            'user_id' => $host->id,
                            'exception' => $exception->getMessage(),
                        ]);
                    }
                }

                // Also when an e-mail failed: no second try an hour later.
                GuestMessage::whereKey($messages->modelKeys())->update(['hosts_notified_at' => now()]);
            });

        $this->info("E-mails enviados: {$emails}.");

        return self::SUCCESS;
    }
}
