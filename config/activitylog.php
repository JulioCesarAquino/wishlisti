<?php

use Spatie\Activitylog\Actions\CleanActivityLogAction;
use Spatie\Activitylog\Actions\LogActivityAction;
use Spatie\Activitylog\Models\Activity;

return [
    'enabled' => env('ACTIVITYLOG_ENABLED', true),

    'clean_after_days' => 365,

    'default_log_name' => 'default',

    'default_auth_driver' => null,

    // Guests, gifts and events go to a trash before being purged, and the
    // audit trail must still show what those entries were about.
    'include_soft_deleted_subjects' => true,

    'activity_model' => Activity::class,

    'default_except_attributes' => [],

    'buffer' => [
        'enabled' => env('ACTIVITYLOG_BUFFER_ENABLED', false),
    ],

    'actions' => [
        'log_activity' => LogActivityAction::class,
        'clean_log' => CleanActivityLogAction::class,
    ],
];
