<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\CariHareketi;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariHareketBelgeTuru;
use App\Muhasebe\Enumlar\CariHareketDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Servisler\CariBakiyeServisi;
use App\Muhasebe\Servisler\CariYaslandirmaServisi;
use App\Services\TenantContextService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CariModuluIleriTest extends TestCase
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

    public function test_tenant_baska_firma_cari_hareketini_goremez(): void
    {
        $fa = $this->firmaOlustur('CHA');
        $fb = $this->firmaOlustur('CHB');

        $cariB = Cari::query()->create([
            'firma_id' => $fb->id,
            'kod' => 'CB-'.uniqid(),
            'ad' => 'Cari B',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        $hareket = CariHareketi::query()->create([
            'firma_id' => $fb->id,
            'cari_id' => $cariB->id,
            'belge_turu' => CariHareketBelgeTuru::Fatura->value,
            'belge_id' => 1,
            'islem_tarihi' => now(),
            'vade_tarihi' => null,
            'borc' => '0',
            'alacak' => '100.00',
            'para_birimi' => 'TRY',
            'durum' => CariHareketDurumu::Aktif->value,
        ]);

        $kullanici = User::query()->create([
            'name' => 'K',
            'email' => 'k-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => false,
        ]);

        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $fa->id]);

        $this->assertNull(CariHareketi::query()->find($hareket->id));
    }

    public function test_cari_olusturma_firma_baglisi(): void
    {
        $f = $this->firmaOlustur('CF');
        $cari = Cari::query()->create([
            'firma_id' => $f->id,
            'kod' => 'K-'.uniqid(),
            'ad' => 'X',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        $this->assertSame((int) $f->id, (int) $cari->firma_id);
    }

    public function test_bakiye_hesabi_borc_alacak(): void
    {
        $kullanici = User::query()->create([
            'name' => 'T',
            'email' => 't-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => false,
        ]);

        $f = $this->firmaOlustur('BAK');
        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $f->id]);

        $cari = Cari::query()->create([
            'firma_id' => $f->id,
            'kod' => 'K-'.uniqid(),
            'ad' => 'X',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        CariHareketi::query()->create([
            'firma_id' => $f->id,
            'cari_id' => $cari->id,
            'belge_turu' => CariHareketBelgeTuru::Fatura->value,
            'belge_id' => 1,
            'islem_tarihi' => now(),
            'vade_tarihi' => null,
            'borc' => '100.00',
            'alacak' => '0',
            'para_birimi' => 'TRY',
            'durum' => CariHareketDurumu::Aktif->value,
        ]);
        CariHareketi::query()->create([
            'firma_id' => $f->id,
            'cari_id' => $cari->id,
            'belge_turu' => CariHareketBelgeTuru::Tahsilat->value,
            'belge_id' => 2,
            'islem_tarihi' => now(),
            'vade_tarihi' => null,
            'borc' => '0',
            'alacak' => '40.00',
            'para_birimi' => 'TRY',
            'durum' => CariHareketDurumu::Aktif->value,
        ]);

        $ozet = app(CariBakiyeServisi::class)->paraBirimiOzetleri((int) $f->id, (int) $cari->id)->first();
        $this->assertNotNull($ozet);
        $this->assertSame('60.00', $ozet->bakiye);
    }

    public function test_yaslandirma_gecikmis_satir_bucket(): void
    {
        $kullanici = User::query()->create([
            'name' => 'T2',
            'email' => 't2-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => false,
        ]);

        $f = $this->firmaOlustur('YAS');
        $this->actingAs($kullanici);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $f->id]);

        $cari = Cari::query()->create([
            'firma_id' => $f->id,
            'kod' => 'K-'.uniqid(),
            'ad' => 'Yaş Cari',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-15'));

        CariHareketi::query()->create([
            'firma_id' => $f->id,
            'cari_id' => $cari->id,
            'belge_turu' => CariHareketBelgeTuru::Fatura->value,
            'belge_id' => 9,
            'islem_tarihi' => Carbon::parse('2026-05-01'),
            'vade_tarihi' => Carbon::parse('2026-05-10'),
            'borc' => '0',
            'alacak' => '50.00',
            'para_birimi' => 'TRY',
            'durum' => CariHareketDurumu::Aktif->value,
        ]);

        $satirlar = app(CariYaslandirmaServisi::class)->rapor((int) $f->id, 'TRY');
        $satir = $satirlar->firstWhere('cari_id', (int) $cari->id);
        $this->assertNotNull($satir);
        // Vade 10.05, rapor tarihi 15.06 → ~36 gün gecikme → 31–60 gün kovası; net = borç−alacak = −50
        $this->assertSame('-50.00', $satir['gun_30_60']);

        Carbon::setTestNow();
    }
}
