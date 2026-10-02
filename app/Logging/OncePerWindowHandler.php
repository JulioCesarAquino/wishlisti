<?php

namespace App\Logging;

use Illuminate\Support\Facades\Cache;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\HandlerWrapper;
use Monolog\LogRecord;
use Throwable;

/**
 * Passes each message on once per window: an error repeating on every
 * request gives one alert, not hundreds. Unlike Monolog's
 * DeduplicationHandler it doesn't buffer until the process ends — the
 * scheduler never ends.
 */
class OncePerWindowHandler extends HandlerWrapper
{
    public function __construct(HandlerInterface $handler, protected int $minutes)
    {
        parent::__construct($handler);
    }

    public function handle(LogRecord $record): bool
    {
        if (! $this->isHandling($record)) {
            return false;
        }

        try {
            $isNew = Cache::add('log-alert:'.sha1($record->level->name.$record->message), true, now()->addMinutes($this->minutes));
        } catch (Throwable) {
            // Without the cache, better a repeated alert than none.
            $isNew = true;
        }

        return $isNew ? parent::handle($record) : false;
    }
}
