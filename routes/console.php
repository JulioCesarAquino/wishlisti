<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('trash:purge')->daily();
Schedule::command('messages:notify-hosts')->hourly()->withoutOverlapping();
Schedule::command('orders:expire-abandoned')->everyTenMinutes()->withoutOverlapping();
Schedule::command('events:close-ended-tabs')->hourly()->withoutOverlapping();
Schedule::command('gallery:purge-free')->dailyAt('04:00')->timezone('America/Sao_Paulo')->withoutOverlapping();
// The gallery's light copies, a batch a minute (see GalleryPreviewsCommand).
Schedule::command('gallery:previews --limit=40')->everyMinute()->withoutOverlapping(10);
