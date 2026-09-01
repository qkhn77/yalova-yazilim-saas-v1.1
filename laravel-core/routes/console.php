<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Shared-hosting PHP yapılandırmasında proc_open kapalı olabilir. Laravel'in
| Schedule::command() olayları alt süreç açtığı için görevleri aynı PHP süreci
| içinde Artisan::call() ile çalıştıran callback olayları kullanılır.
*/
$scheduleCommand = static function (string $command, array $parameters = []) {
    $parameterSummary = collect($parameters)
        ->map(fn ($value, string $key): string => $key.'='.(is_bool($value) ? ($value ? '1' : '0') : (string) $value))
        ->implode(' ');
    $description = trim('artisan:'.$command.' '.$parameterSummary);

    return Schedule::call(static function () use ($command, $parameters): void {
        $exitCode = Artisan::call($command, $parameters);

        if ($exitCode !== 0) {
            throw new RuntimeException($command.' zamanlanmış görevi hata koduyla tamamlandı: '.$exitCode);
        }
    })->name($description);
};

$scheduleCommand('inspire')->hourly();
$scheduleCommand('siparis:odeme-zaman-asimi-isle')->everyMinute();
$scheduleCommand('ecommerce:mesaj-sla-kontrol')->everyFiveMinutes();
$scheduleCommand('ecommerce:pazaryeri-siparis-cek')->everyFiveMinutes();
$scheduleCommand('muhasebe:doviz-kurlari-guncelle')->dailyAt('09:15');
$scheduleCommand('barkodlu-satis:mutabakat-dogrula', ['--days' => 2, '--limit' => 1500])->dailyAt('03:25');
$scheduleCommand('muhasebe:alacak-plan-dogrula', ['--limit' => 5000])->dailyAt('03:40');
$scheduleCommand('muhasebe:vade-hatirlatma', ['--days' => 7, '--cache' => true])->dailyAt('08:10');
$scheduleCommand('personel:devamsizlik-isle')->dailyAt('02:20');
$scheduleCommand('sekreter:hatirlatmalari-gonder')->everyMinute();
