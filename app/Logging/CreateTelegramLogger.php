<?php

namespace App\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\TelegramBotHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Level;
use Monolog\Logger;

/**
 * The "telegram" log channel: errors go to the admin's Telegram as they
 * happen, so nobody has to watch the server's logs. Without a bot token
 * and chat id (local, tests), it does nothing.
 */
class CreateTelegramLogger
{
    /**
     * @param  array{token?: ?string, chat_id?: ?string, level?: ?string, quiet_minutes?: int|string|null}  $config
     */
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('telegram');

        if (blank($config['token'] ?? null) || blank($config['chat_id'] ?? null)) {
            return $logger->pushHandler(new NullHandler);
        }

        $level = strtolower((string) ($config['level'] ?? 'error'));

        $telegram = new TelegramBotHandler(
            apiKey: (string) $config['token'],
            channel: (string) $config['chat_id'],
            level: in_array($level, ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'], true)
                ? Level::fromName($level)
                : Level::Error,
            disableWebPagePreview: true,
            splitLongMessages: true,
        );

        // Short and readable on a phone: no stack trace, which stays in the
        // log file (and on the /admin/logs screen).
        $telegram->setFormatter(new LineFormatter(
            '['.config('app.name').' · '.config('app.env')."] %level_name%\n%message%\n%context%",
            null,
            true,
            true,
        ));

        // Telegram down or slow must never break a request or a job.
        return $logger->pushHandler(new WhatFailureGroupHandler([
            new OncePerWindowHandler($telegram, (int) ($config['quiet_minutes'] ?? 10)),
        ]));
    }
}
