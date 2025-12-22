<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| Define Artisan commands and scheduling for the temp mail server.
|
*/

// Schedule cleanup of expired inboxes every hour
Schedule::command('inbox:cleanup')->hourly();
