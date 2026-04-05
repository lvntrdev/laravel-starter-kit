<?php

use Spatie\Activitylog\Actions\CleanActivityLogAction;
use Spatie\Activitylog\Actions\LogActivityAction;
use Spatie\Activitylog\Models\Activity;

return [

    /*
     |--------------------------------------------------------------------------
     | Activity Logger Toggle
     |--------------------------------------------------------------------------
     |
     | Set to false to completely disable activity logging.
     | When disabled, no activities will be saved to the database.
     | This makes spatie/laravel-activitylog an optional feature.
     |
     */
    'enabled' => env('ACTIVITYLOG_ENABLED', true),

    /*
     |--------------------------------------------------------------------------
     | Record Retention
     |--------------------------------------------------------------------------
     |
     | When the clean-command is executed, all recording activities older than
     | the number of days specified here will be deleted.
     |
     */
    'clean_after_days' => 365,

    /*
     |--------------------------------------------------------------------------
     | Default Log Name
     |--------------------------------------------------------------------------
     |
     | If no log name is passed to the activity() helper
     | we use this default log name.
     |
     */
    'default_log_name' => 'default',

    /*
     |--------------------------------------------------------------------------
     | Auth Driver
     |--------------------------------------------------------------------------
     |
     | You can specify an auth driver here that gets user models.
     | If this is null we'll use the current Laravel auth driver.
     |
     */
    'default_auth_driver' => null,

    /*
     |--------------------------------------------------------------------------
     | Soft Deleted Models
     |--------------------------------------------------------------------------
     |
     | If set to true, the subject relationship on activities
     | will include soft deleted models.
     |
     */
    'include_soft_deleted_subjects' => true,

    /*
     |--------------------------------------------------------------------------
     | Activity Model
     |--------------------------------------------------------------------------
     |
     | This model will be used to log activity.
     | It should implement the Spatie\Activitylog\Contracts\Activity interface
     | and extend Illuminate\Database\Eloquent\Model.
     |
     */
    'activity_model' => Activity::class,

    /*
     |--------------------------------------------------------------------------
     | Default Excluded Attributes
     |--------------------------------------------------------------------------
     |
     | These attributes will be excluded from logging for all models.
     | Model-specific exclusions via logExcept() are merged with these.
     |
     */
    'default_except_attributes' => [],

    /*
     |--------------------------------------------------------------------------
     | Buffered Logging
     |--------------------------------------------------------------------------
     |
     | When enabled, activities are buffered in memory and inserted in a
     | single bulk query after the response has been sent to the client.
     | Only enable this if your application logs a high volume of activities
     | per request.
     |
     */
    'buffer' => [
        'enabled' => env('ACTIVITYLOG_BUFFER_ENABLED', false),
    ],

    /*
     |--------------------------------------------------------------------------
     | Action Classes
     |--------------------------------------------------------------------------
     |
     | These action classes can be overridden to customize how activities
     | are logged and cleaned. Your custom classes must extend the originals.
     |
     */
    'actions' => [
        'log_activity' => LogActivityAction::class,
        'clean_log' => CleanActivityLogAction::class,
    ],
];
