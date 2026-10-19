<?php

use Illuminate\Support\Facades\Schedule;

// Cron wajib di VM: * * * * * cd /var/www/bk-pusat && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('bk:bersihkan')->dailyAt('02:30');
Schedule::command('bk:sinkron')->hourly()->when(fn () => (bool) config('pusat.sinkron_otomatis'));
