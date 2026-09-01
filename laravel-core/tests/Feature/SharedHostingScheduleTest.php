<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class SharedHostingScheduleTest extends TestCase
{
    public function test_tum_zamanlanmis_gorevler_proc_open_gerektirmeyen_callback_kullanir(): void
    {
        $events = app(Schedule::class)->events();

        $this->assertCount(10, $events);

        foreach ($events as $event) {
            $this->assertInstanceOf(CallbackEvent::class, $event);
            $this->assertStringStartsWith('artisan:', $event->getSummaryForDisplay());
        }
    }

    public function test_zamanlama_ifadeleri_korunur(): void
    {
        $expressions = collect(app(Schedule::class)->events())
            ->mapWithKeys(fn ($event): array => [$event->getSummaryForDisplay() => $event->expression]);

        $this->assertSame('* * * * *', $expressions['artisan:siparis:odeme-zaman-asimi-isle']);
        $this->assertSame('*/5 * * * *', $expressions['artisan:ecommerce:mesaj-sla-kontrol']);
        $this->assertSame('15 9 * * *', $expressions['artisan:muhasebe:doviz-kurlari-guncelle']);
        $this->assertSame('25 3 * * *', $expressions['artisan:barkodlu-satis:mutabakat-dogrula --days=2 --limit=1500']);
        $this->assertSame('10 8 * * *', $expressions['artisan:muhasebe:vade-hatirlatma --days=7 --cache=1']);
    }
}
