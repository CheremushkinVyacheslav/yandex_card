<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

# shared-хостинг: воркер поднимается раз в минуту, выгребает очередь и завершается;
# без демона queue:work, мутекс не даёт запустить второй воркер поверх живого
Schedule::command('queue:work --stop-when-empty --tries=3 --timeout=300')
    ->everyMinute()
    ->withoutOverlapping();
