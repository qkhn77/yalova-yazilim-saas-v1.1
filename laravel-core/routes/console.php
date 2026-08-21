<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('siparis:odeme-zaman-asimi-isle')->everyMinute();
Schedule::command('ecommerce:mesaj-sla-kontrol')->everyFiveMinutes();
Schedule::command('ecommerce:pazaryeri-siparis-cek')->everyFiveMinutes();
Schedule::command('muhasebe:doviz-kurlari-guncelle')->dailyAt('09:15');
Schedule::command('barkodlu-satis:mutabakat-dogrula --days=2 --limit=1500')->dailyAt('03:25');
Schedule::command('muhasebe:alacak-plan-dogrula --limit=5000')->dailyAt('03:40');
Schedule::command('muhasebe:vade-hatirlatma --days=7 --cache')->dailyAt('08:10');
Schedule::command('personel:devamsizlik-isle')->dailyAt('02:20');
Schedule::command('sekreter:hatirlatmalari-gonder')->everyMinute();
