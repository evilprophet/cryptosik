<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment('Cryptosik scheduler is active.');
})->purpose('Display scheduler health status.');

Schedule::command('cryptosik:verify-chains')->cron('0 */3 * * *');
Schedule::command('cryptosik:otp-prune')->hourly();
Schedule::command('cryptosik:notifications:weekly-unread')->cron((string) config('cryptosik.notifications.weekly_unread_cron', '0 9 * * 6'));
Schedule::command('cryptosik:audit-logs-prune')->cron((string) config('cryptosik.audit_logs.prune_cron', '15 3 * * *'));
