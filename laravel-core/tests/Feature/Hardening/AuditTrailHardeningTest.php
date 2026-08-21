<?php

namespace Tests\Feature\Hardening;

use App\Console\Commands\MuhasebeMinimumExportCommand;
use App\Filament\Clusters\Muhasebe\Resources\CariKartiKaynagi\Pages\EditCari;
use App\Models\DenetimKayidi;
use App\Models\Ecommerce\Siparis;
use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\CariHareketi;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Models\User;
use App\Modules\Urun\Servisler\SiparisOdemeServisi;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Enumlar\FinansHareketTuru;
use App\Services\EcommerceOdemeFirmaAyarServisi;
use App\Services\ReconciliationBakimServisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuditTrailHardeningTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_fatura_durum_degisiminde_audit_kaydi_uretilir(): void
    {
        $firma = $this->firmaOlustur('ADT-F');
        $cari = Cari::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kod' => 'CF-'.uniqid(),
            'ad' => 'Cari',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        $fatura = Fatura::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'tur' => FaturaTuru::Giden->value,
            'durum' => FaturaDurumu::Taslak->value,
            'tarih' => now(),
            'ara_toplam' => '100',
            'kdv_toplam' => '20',
            'genel_toplam' => '120',
            'odenecek_tutar' => '120',
            'acik_tutar' => '120',
            'odendi_tutari' => '0',
            'para_birimi' => 'TRY',
        ]);

        $fatura->update(['durum' => FaturaDurumu::Onayli->value, 'odeme_durumu' => 'odenmedi']);

        $this->assertDatabaseHas('denetim_kayitlari', [
            'olay' => 'fatura.guncelle',
            'konu_tipi' => Fatura::class,
            'konu_id' => $fatura->id,
        ]);
    }

    public function test_cari_para_birimi_degisim_denemesi_auditlenir(): void
    {
        $firma = $this->firmaOlustur('ADT-C');
        $user = User::query()->create([
            'name' => 'SA',
            'email' => 'sa-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);
        $this->actingAs($user);

        $cari = Cari::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kod' => 'CC-'.uniqid(),
            'ad' => 'Cari',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        CariHareketi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'belge_turu' => 'fatura',
            'belge_id' => 1,
            'islem_tarihi' => now(),
            'borc' => '10.00',
            'alacak' => '0.00',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);

        $page = new class extends EditCari
        {
            public function mutateForTest(Cari $record, array $data): array
            {
                $this->record = $record;

                return $this->mutateFormDataBeforeSave($data);
            }
        };

        $this->expectException(ValidationException::class);
        $page->mutateForTest($cari, [
            'firma_id' => $firma->id,
            'kod' => $cari->kod,
            'para_birimi' => 'USD',
        ]);

        $this->assertDatabaseHas('denetim_kayitlari', [
            'olay' => 'cari.para_birimi_degisim_engellendi',
            'konu_tipi' => Cari::class,
            'konu_id' => $cari->id,
        ]);
    }

    public function test_stok_kritik_alan_degisiminde_audit_kaydi_uretilir(): void
    {
        $firma = $this->firmaOlustur('ADT-S');
        $stok = StokKarti::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kod' => 'STK-'.uniqid(),
            'ad' => 'Ürün',
            'slug' => 'urun-'.uniqid(),
            'tur' => 'ticari_mal',
            'durum' => 'aktif',
            'birim' => 'AD',
            'satis_fiyati' => '100.00',
            'alis_fiyati' => '80.00',
            'stok_takip' => true,
            'minimum_stok' => '1.0000',
            'stok_miktari' => '5.0000',
            'para_birimi' => 'TRY',
            'kdv_orani' => '20.00',
        ]);

        $stok->update(['satis_fiyati' => '120.00', 'minimum_stok' => '2.0000']);

        $this->assertDatabaseHas('denetim_kayitlari', [
            'olay' => 'stok_karti.guncelle',
            'konu_tipi' => StokKarti::class,
            'konu_id' => $stok->id,
        ]);
    }

    public function test_siparis_iptalinde_audit_kaydi_uretilir(): void
    {
        $firma = $this->firmaOlustur('ADT-O');
        $siparis = Siparis::withoutGlobalScopes()->create([
            'siparis_no' => 'SIP-'.uniqid(),
            'firma_id' => $firma->id,
            'musteri_ad_soyad' => 'Test',
            'musteri_email' => 'm@test.local',
            'musteri_telefon' => '555',
            'teslimat_adresi' => 'Adres',
            'para_birimi' => 'TRY',
            'ara_toplam' => '100.00',
            'kdv_toplam' => '20.00',
            'genel_toplam' => '120.00',
            'durum' => Siparis::DURUM_ONAY_BEKLIYOR,
            'stok_dusuldu_mi' => false,
        ]);

        app(SiparisOdemeServisi::class)->siparisIptalEt($siparis, 'test iptal');

        $this->assertDatabaseHas('denetim_kayitlari', [
            'olay' => 'siparis.guncelle',
            'konu_tipi' => Siparis::class,
            'konu_id' => $siparis->id,
        ]);
    }

    public function test_odeme_ayari_degisimi_auditlenir_ve_secret_sizmaz(): void
    {
        $firma = $this->firmaOlustur('ADT-P');
        app(EcommerceOdemeFirmaAyarServisi::class)->kaydetAyarlar((int) $firma->id, [
            'ecommerce_odeme_aktif_mi' => true,
            'ecommerce_odeme_provider' => 'paytr',
            'test_modu' => true,
            'paytr_merchant_key' => 'SECRET-123',
        ]);

        $kayit = DenetimKayidi::query()->where('olay', 'odeme_ayari.guncelle')->latest('id')->first();
        $this->assertNotNull($kayit);
        $json = json_encode($kayit?->yeni_veri, JSON_UNESCAPED_UNICODE);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('SECRET-123', $json);
        $this->assertStringContainsString('secret_degisti', $json);
    }

    public function test_export_islemi_loglanir(): void
    {
        $firma = $this->firmaOlustur('ADT-E');
        $this->artisan('muhasebe:export-minimum', [
            '--firma_id' => $firma->id,
            '--from' => now()->startOfMonth()->format('Y-m-d'),
            '--to' => now()->format('Y-m-d'),
            '--para_birimi' => 'TRY',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('denetim_kayitlari', [
            'olay' => 'export.olusturuldu',
            'konu_tipi' => MuhasebeMinimumExportCommand::class,
        ]);
    }

    public function test_reconciliation_fix_audit_kaydi_uretir(): void
    {
        $firma = $this->firmaOlustur('ADT-R');
        $cari = Cari::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kod' => 'CR-'.uniqid(),
            'ad' => 'Cari',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        $finans = FinansHareketi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'tur' => FinansHareketTuru::Tahsilat->value,
            'tarih' => now(),
            'tutar' => '100.00',
            'para_birimi' => 'TRY',
            'cari_id' => $cari->id,
            'durum' => FinansHareketDurumu::Aktif->value,
            'kullanilan_tutar' => '0.00',
            'avans_tutar' => '0.00',
        ]);

        $sonuc = app(ReconciliationBakimServisi::class)->muhasebeReconcile((int) $firma->id, true);
        $this->assertGreaterThanOrEqual(1, $sonuc['duzeltilen']);

        $this->assertDatabaseHas('denetim_kayitlari', [
            'olay' => 'reconcile.fix_basarili',
            'konu_tipi' => FinansHareketi::class,
            'konu_id' => $finans->id,
        ]);
    }
}
