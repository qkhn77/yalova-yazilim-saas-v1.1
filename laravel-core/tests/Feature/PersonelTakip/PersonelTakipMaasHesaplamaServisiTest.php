<?php

namespace Tests\Feature\PersonelTakip;

use App\Models\Firma;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelAvansi;
use App\Models\Personel\PersonelGirisCikisi;
use App\Models\Personel\PersonelMaasDonemi;
use App\Models\Personel\PersonelMaasHareketi;
use App\Models\Personel\PersonelMaasKalemi;
use App\Models\Personel\PersonelVardiyasi;
use App\Services\PersonelTakip\PersonelAyarlariServisi;
use App\Services\PersonelTakip\PersonelMaasHesaplamaServisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PersonelTakipMaasHesaplamaServisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_maas_donemi_aylik_fazla_mesai_ve_avansi_hesaplar(): void
    {
        $firma = $this->firmaOlustur('MHS');
        $personel = $this->personelOlustur($firma, [
            'ad_soyad' => 'Aylık Personel',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 10000,
            'saatlik_ucret' => 100,
        ]);
        $donem = $this->donemOlustur($firma);
        $vardiya = $this->vardiyaOlustur($firma, $personel);

        PersonelGirisCikisi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'vardiya_id' => $vardiya->id,
            'giris_at' => '2026-05-10 10:00:00',
            'cikis_at' => '2026-05-10 19:00:00',
            'onay_durumu' => 'onaylandi',
        ]);

        PersonelAvansi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-05-15',
            'tutar' => 1000,
            'durum' => 'onaylandi',
        ]);

        $ozet = app(PersonelMaasHesaplamaServisi::class)->donemiHesapla($donem);
        $hareket = PersonelMaasHareketi::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(1, $ozet['hareket_sayisi']);
        $this->assertSame('10000.00', $hareket->brut_tutar);
        $this->assertSame('150.00', $hareket->fazla_mesai_tutari);
        $this->assertSame('1000.00', $hareket->avans_kesintisi);
        $this->assertSame('9150.00', $hareket->net_tutar);
        $this->assertSame('9150.00', $hareket->kalan_tutar);
        $this->assertSame('9150.00', $donem->fresh()->toplam_net);
    }

    public function test_gunluk_personel_onayli_calisilan_gune_gore_hesaplanir(): void
    {
        $firma = $this->firmaOlustur('MGN');
        $personel = $this->personelOlustur($firma, [
            'ad_soyad' => 'Günlük Personel',
            'maas_tipi' => 'gunluk',
            'maas_tutari' => 0,
            'gunluk_ucret' => 500,
        ]);
        $donem = $this->donemOlustur($firma);

        foreach (['2026-05-10', '2026-05-11'] as $tarih) {
            PersonelGirisCikisi::withoutGlobalScopes()->create([
                'firma_id' => $firma->id,
                'personel_id' => $personel->id,
                'giris_at' => $tarih.' 10:00:00',
                'cikis_at' => $tarih.' 18:00:00',
                'onay_durumu' => 'onaylandi',
            ]);
        }

        app(PersonelMaasHesaplamaServisi::class)->donemiHesapla($donem);
        $hareket = PersonelMaasHareketi::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('1000.00', $hareket->brut_tutar);
        $this->assertSame('1000.00', $hareket->net_tutar);
    }

    public function test_fazla_mesai_katsayisi_personel_ayarlarindan_okunur(): void
    {
        $firma = $this->firmaOlustur('MAY');
        app(PersonelAyarlariServisi::class)->kaydetGenel($firma->id, [
            'fazla_mesai_katsayi' => 2,
        ]);
        $personel = $this->personelOlustur($firma, [
            'ad_soyad' => 'Ayarli Mesai Personeli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 10000,
            'saatlik_ucret' => 100,
        ]);
        $donem = $this->donemOlustur($firma);
        $vardiya = $this->vardiyaOlustur($firma, $personel);

        PersonelGirisCikisi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'vardiya_id' => $vardiya->id,
            'giris_at' => '2026-05-10 10:00:00',
            'cikis_at' => '2026-05-10 19:00:00',
            'onay_durumu' => 'onaylandi',
        ]);

        app(PersonelMaasHesaplamaServisi::class)->donemiHesapla($donem);
        $hareket = PersonelMaasHareketi::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('200.00', $hareket->fazla_mesai_tutari);
        $this->assertSame('10200.00', $hareket->net_tutar);
    }

    public function test_maas_hesaplama_tekrarlandiginda_hareket_ve_kalem_cogaltmaz(): void
    {
        $firma = $this->firmaOlustur('MTR');
        $personel = $this->personelOlustur($firma, [
            'ad_soyad' => 'Tekrar Personeli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 8000,
        ]);
        $donem = $this->donemOlustur($firma);

        app(PersonelMaasHesaplamaServisi::class)->donemiHesapla($donem);
        app(PersonelMaasHesaplamaServisi::class)->donemiHesapla($donem);

        $this->assertSame(1, PersonelMaasHareketi::withoutGlobalScopes()->where('personel_id', $personel->id)->count());
        $this->assertSame(1, PersonelMaasKalemi::withoutGlobalScopes()->count());
    }

    public function test_onaylanmis_maas_donemi_yeniden_hesaplanamaz(): void
    {
        $firma = $this->firmaOlustur('MOK');
        $this->personelOlustur($firma, [
            'ad_soyad' => 'Kilitli Maaş Personeli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 8000,
        ]);
        $donem = $this->donemOlustur($firma);

        app(PersonelMaasHesaplamaServisi::class)->donemiHesapla($donem);
        $donem->forceFill(['durum' => 'onaylandi'])->save();

        $this->expectException(ValidationException::class);

        app(PersonelMaasHesaplamaServisi::class)->donemiHesapla($donem);
    }

    public function test_maas_hesaplama_komutu_donemi_hesaplar(): void
    {
        $firma = $this->firmaOlustur('MCK');
        $this->personelOlustur($firma, [
            'ad_soyad' => 'Komut Personeli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 7000,
        ]);
        $donem = $this->donemOlustur($firma);

        $this->artisan('personel:maas-hesapla', [
            'donem_id' => $donem->id,
            '--firma' => $firma->id,
        ])
            ->expectsOutput('Personel maaş dönemi hesaplandı.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('personel_maas_hareketleri', [
            'firma_id' => $firma->id,
            'maas_donemi_id' => $donem->id,
            'net_tutar' => '7000.00',
        ]);
    }

    public function test_maas_hesaplama_komutu_firma_dogrulamasi_yapar(): void
    {
        $firma = $this->firmaOlustur('MCF');
        $donem = $this->donemOlustur($firma);

        $this->artisan('personel:maas-hesapla', [
            'donem_id' => $donem->id,
            '--firma' => $firma->id + 1000,
        ])
            ->expectsOutput('Firma doğrulaması başarısız. Dönem farklı firmaya ait.')
            ->assertExitCode(1);
    }

    public function test_maas_hesaplama_komutu_kilitli_donemde_basarisiz_olur(): void
    {
        $firma = $this->firmaOlustur('MKK');
        $donem = $this->donemOlustur($firma);
        $donem->forceFill(['durum' => 'onaylandi'])->save();

        $this->artisan('personel:maas-hesapla', [
            'donem_id' => $donem->id,
            '--firma' => $firma->id,
        ])
            ->expectsOutput('Onaylanmış veya ödenmiş maaş dönemi yeniden hesaplanamaz.')
            ->assertExitCode(1);
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

    /** @param array<string, mixed> $ek */
    private function personelOlustur(Firma $firma, array $ek): Personel
    {
        return Personel::withoutGlobalScopes()->create(array_merge([
            'firma_id' => $firma->id,
            'ad_soyad' => 'Maaş Personeli',
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 10000,
            'durum' => Personel::DURUM_AKTIF,
        ], $ek));
    }

    private function donemOlustur(Firma $firma): PersonelMaasDonemi
    {
        return PersonelMaasDonemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'baslangic_tarihi' => '2026-05-01',
            'bitis_tarihi' => '2026-05-31',
            'durum' => 'taslak',
        ]);
    }

    private function vardiyaOlustur(Firma $firma, Personel $personel): PersonelVardiyasi
    {
        return PersonelVardiyasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-05-10',
            'baslangic_at' => '2026-05-10 10:00:00',
            'bitis_at' => '2026-05-10 18:00:00',
            'durum' => 'planlandi',
        ]);
    }
}
