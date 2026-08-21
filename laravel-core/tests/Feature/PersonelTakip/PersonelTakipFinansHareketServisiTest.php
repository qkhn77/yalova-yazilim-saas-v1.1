<?php

namespace Tests\Feature\PersonelTakip;

use App\Models\Firma;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelAvansi;
use App\Models\Personel\PersonelMaasDonemi;
use App\Models\Personel\PersonelMaasHareketi;
use App\Models\Personel\PersonelMaasOdemeKaydi;
use App\Models\User;
use App\Services\PersonelTakip\PersonelFinansHareketServisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PersonelTakipFinansHareketServisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_personel_avansi_kasa_finans_hareketi_olusturur_ve_idempotenttir(): void
    {
        $firma = $this->firmaOlustur('PFK');
        $personel = $this->personelOlustur($firma);
        $kasa = $this->kasaOlustur($firma);
        $avans = PersonelAvansi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-05-31',
            'tutar' => 1000,
            'para_birimi' => 'TRY',
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
            'durum' => 'onaylandi',
        ]);

        $servis = app(PersonelFinansHareketServisi::class);
        $finans = $servis->avansOdemesiniFinansaIsle($avans);
        $ikinci = $servis->avansOdemesiniFinansaIsle($avans->fresh());

        $this->assertSame($finans->id, $ikinci->id);
        $this->assertSame('personel_avans', $finans->referans_turu);
        $this->assertSame('Personel Takip', $finans->modul_etiketi);
        $this->assertSame($finans->id, $avans->fresh()->finans_hareketi_id);
        $this->assertSame(1, FinansHareketi::withoutGlobalScopes()->count());
        $this->assertDatabaseHas('kasa_hareketleri', [
            'firma_id' => $firma->id,
            'finans_hareket_id' => $finans->id,
            'kasa_hesap_id' => $kasa->id,
            'tutar' => '-1000.00',
        ]);
    }

    public function test_maas_odeme_banka_finans_hareketi_olusturur(): void
    {
        $firma = $this->firmaOlustur('PFB');
        $banka = $this->bankaOlustur($firma);
        $hareket = $this->maasHareketiOlustur($firma, 5000);
        $odeme = PersonelMaasOdemeKaydi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'maas_hareketi_id' => $hareket->id,
            'tarih' => '2026-05-31',
            'tutar' => 3000,
            'para_birimi' => 'TRY',
            'odeme_kanali' => 'banka',
            'banka_hesap_id' => $banka->id,
        ]);

        $finans = app(PersonelFinansHareketServisi::class)->maasOdemesiniFinansaIsle($odeme);

        $this->assertSame('personel_maas_odeme', $finans->referans_turu);
        $this->assertSame($finans->id, $odeme->fresh()->finans_hareketi_id);
        $this->assertDatabaseHas('banka_hareketleri', [
            'firma_id' => $firma->id,
            'finans_hareket_id' => $finans->id,
            'banka_hesap_id' => $banka->id,
            'tutar' => '-3000.00',
        ]);
    }

    public function test_personel_finans_isleminde_hesap_para_birimi_uyumlu_olmalidir(): void
    {
        $firma = $this->firmaOlustur('PFD');
        $personel = $this->personelOlustur($firma);
        $kasa = $this->kasaOlustur($firma, 'USD');
        $avans = PersonelAvansi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'tarih' => '2026-05-31',
            'tutar' => 1000,
            'para_birimi' => 'TRY',
            'odeme_kanali' => 'diger',
            'durum' => 'onaylandi',
        ]);
        $avans->forceFill([
            'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id,
        ])->saveQuietly();

        $this->expectException(ValidationException::class);

        app(PersonelFinansHareketServisi::class)->avansOdemesiniFinansaIsle($avans->fresh());
    }

    public function test_personel_avans_iptali_finans_ve_kasa_hareketini_tersler(): void
    {
        $firma = $this->firmaOlustur('PFE');
        $this->actingAs(User::factory()->create(['super_admin_mi' => true]));
        $personel = $this->personelOlustur($firma);
        $kasa = $this->kasaOlustur($firma);
        $avans = PersonelAvansi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id, 'personel_id' => $personel->id, 'tarih' => '2026-05-31',
            'tutar' => 1000, 'para_birimi' => 'TRY', 'odeme_kanali' => 'kasa',
            'kasa_hesap_id' => $kasa->id, 'durum' => 'onaylandi',
        ]);
        $finans = app(PersonelFinansHareketServisi::class)->avansOdemesiniFinansaIsle($avans);

        app(PersonelFinansHareketServisi::class)->avansOdemesiniIptalEt($avans->fresh());

        $this->assertSame('iptal', (string) FinansHareketi::withoutGlobalScopes()->findOrFail($finans->id)->getRawOriginal('durum'));
        $this->assertSame('iptal', $avans->fresh()->durum);
        $this->assertDatabaseHas('finans_hareketleri', ['iptal_edilen_hareket_id' => $finans->id, 'durum' => 'aktif']);
    }

    public function test_personel_maas_odemesi_iptal_edilince_finans_terslenir(): void
    {
        $firma = $this->firmaOlustur('PFF');
        $this->actingAs(User::factory()->create(['super_admin_mi' => true]));
        $banka = $this->bankaOlustur($firma);
        $hareket = $this->maasHareketiOlustur($firma, 5000);
        $odeme = PersonelMaasOdemeKaydi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id, 'maas_hareketi_id' => $hareket->id, 'tarih' => '2026-05-31',
            'tutar' => 3000, 'para_birimi' => 'TRY', 'odeme_kanali' => 'banka', 'banka_hesap_id' => $banka->id,
        ]);
        $finans = app(PersonelFinansHareketServisi::class)->maasOdemesiniFinansaIsle($odeme);

        app(PersonelFinansHareketServisi::class)->maasOdemesiniIptalEt($odeme->fresh());

        $this->assertSame('iptal', (string) FinansHareketi::withoutGlobalScopes()->findOrFail($finans->id)->getRawOriginal('durum'));
        $this->assertDatabaseHas('finans_hareketleri', ['iptal_edilen_hareket_id' => $finans->id, 'durum' => 'aktif']);
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

    private function personelOlustur(Firma $firma): Personel
    {
        return Personel::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad_soyad' => 'Finans Personeli',
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 5000,
            'durum' => Personel::DURUM_AKTIF,
        ]);
    }

    private function maasHareketiOlustur(Firma $firma, int $netTutar): PersonelMaasHareketi
    {
        $personel = $this->personelOlustur($firma);
        $donem = PersonelMaasDonemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'baslangic_tarihi' => '2026-05-01',
            'bitis_tarihi' => '2026-05-31',
            'durum' => 'taslak',
        ]);

        return PersonelMaasHareketi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'maas_donemi_id' => $donem->id,
            'personel_id' => $personel->id,
            'brut_tutar' => $netTutar,
            'durum' => 'taslak',
        ]);
    }

    private function kasaOlustur(Firma $firma, string $paraBirimi = 'TRY'): KasaHesabi
    {
        return KasaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KASA-'.uniqid(),
            'ad' => 'Test Kasası',
            'para_birimi' => $paraBirimi,
        ]);
    }

    private function bankaOlustur(Firma $firma): BankaHesabi
    {
        return BankaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'BANKA-'.uniqid(),
            'ad' => 'Test Bankası',
            'para_birimi' => 'TRY',
        ]);
    }
}
