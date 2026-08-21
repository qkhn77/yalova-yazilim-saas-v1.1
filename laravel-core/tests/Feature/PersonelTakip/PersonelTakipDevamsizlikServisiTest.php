<?php

namespace Tests\Feature\PersonelTakip;

use App\Models\Firma;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelGirisCikisi;
use App\Models\Personel\PersonelIzni;
use App\Models\Personel\PersonelMaasDonemi;
use App\Models\Personel\PersonelMaasHareketi;
use App\Models\Personel\PersonelVardiyasi;
use App\Services\PersonelTakip\PersonelDevamsizlikServisi;
use App\Services\PersonelTakip\PersonelMaasHesaplamaServisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PersonelTakipDevamsizlikServisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_kapanmis_vardiyada_giris_yoksa_devamsizlik_olusturur(): void
    {
        Carbon::setTestNow('2026-06-02 10:00:00');

        $firma = $this->firmaOlustur('PDV');
        $personel = $this->personelOlustur($firma);
        $vardiya = $this->vardiyaOlustur($firma, $personel);

        $ozet = app(PersonelDevamsizlikServisi::class)->firmaIcinIsle($firma->id, '2026-06-01');

        $this->assertSame(1, $ozet['islenen_vardiya']);
        $this->assertSame(1, $ozet['olusturulan_devamsizlik']);
        $this->assertDatabaseHas('personel_izinleri', [
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'izin_turu' => 'devamsizlik',
            'durum' => 'onaylandi',
            'onay_durumu' => 'onaylandi',
            'baslangic_at' => $vardiya->baslangic_at->format('Y-m-d H:i:s'),
        ]);

        $ikinciOzet = app(PersonelDevamsizlikServisi::class)->firmaIcinIsle($firma->id, '2026-06-01');
        $this->assertSame(0, $ikinciOzet['olusturulan_devamsizlik']);

        Carbon::setTestNow();
    }

    public function test_giris_kaydi_olan_vardiya_devamsizlik_olusturmaz(): void
    {
        Carbon::setTestNow('2026-06-02 10:00:00');

        $firma = $this->firmaOlustur('PDG');
        $personel = $this->personelOlustur($firma);
        $vardiya = $this->vardiyaOlustur($firma, $personel);

        PersonelGirisCikisi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'vardiya_id' => $vardiya->id,
            'giris_at' => '2026-06-01 09:05:00',
            'cikis_at' => '2026-06-01 18:00:00',
            'onay_durumu' => 'onaylandi',
        ]);

        $ozet = app(PersonelDevamsizlikServisi::class)->firmaIcinIsle($firma->id, '2026-06-01');

        $this->assertSame(0, $ozet['olusturulan_devamsizlik']);
        $this->assertSame(0, PersonelIzni::withoutGlobalScopes()->where('izin_turu', 'devamsizlik')->count());

        Carbon::setTestNow();
    }

    public function test_devamsizlik_maas_hesabinda_kesinti_olur(): void
    {
        Carbon::setTestNow('2026-06-02 10:00:00');

        $firma = $this->firmaOlustur('PDM');
        $personel = $this->personelOlustur($firma, 30000);
        $this->vardiyaOlustur($firma, $personel);
        app(PersonelDevamsizlikServisi::class)->firmaIcinIsle($firma->id, '2026-06-01');

        $donem = PersonelMaasDonemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'baslangic_tarihi' => '2026-06-01',
            'bitis_tarihi' => '2026-06-30',
            'durum' => 'taslak',
        ]);

        app(PersonelMaasHesaplamaServisi::class)->donemiHesapla($donem);

        $hareket = PersonelMaasHareketi::withoutGlobalScopes()->where('personel_id', $personel->id)->firstOrFail();
        $this->assertSame('1000.00', $hareket->devamsizlik_kesintisi);
        $this->assertSame('29000.00', $hareket->net_tutar);

        Carbon::setTestNow();
    }

    public function test_devamsizlik_komutu_firma_ve_tarih_ile_calisir(): void
    {
        Carbon::setTestNow('2026-06-02 10:00:00');

        $firma = $this->firmaOlustur('PDC');
        $personel = $this->personelOlustur($firma);
        $this->vardiyaOlustur($firma, $personel);

        $this->artisan('personel:devamsizlik-isle', [
            '--firma_id' => $firma->id,
            '--tarih' => '2026-06-01',
        ])
            ->assertExitCode(0)
            ->expectsOutput('Oluşturulan devamsızlık: 1');

        Carbon::setTestNow();
    }

    private function firmaOlustur(string $kod): Firma
    {
        return Firma::query()->create([
            'ad' => 'Test '.$kod,
            'kisa_ad' => $kod,
            'firma_kodu' => $kod.'-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
    }

    private function personelOlustur(Firma $firma, float $maas = 10000): Personel
    {
        return Personel::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad_soyad' => 'Devamsızlık Personeli',
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => $maas,
            'durum' => Personel::DURUM_AKTIF,
        ]);
    }

    private function vardiyaOlustur(Firma $firma, Personel $personel): PersonelVardiyasi
    {
        return PersonelVardiyasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-06-01',
            'baslangic_at' => '2026-06-01 09:00:00',
            'bitis_at' => '2026-06-01 18:00:00',
            'durum' => 'planlandi',
        ]);
    }
}
