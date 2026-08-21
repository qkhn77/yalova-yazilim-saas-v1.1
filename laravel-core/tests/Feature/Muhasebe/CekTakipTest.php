<?php

namespace Tests\Feature\Muhasebe;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Cek;
use App\Models\Muhasebe\CekHareketi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\CekDurumu;
use App\Muhasebe\Enumlar\CekHareketDurumu;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\CekServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CekTakipTest extends TestCase
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

    private function superAdminVeSession(Firma $firma): void
    {
        $user = User::query()->create([
            'name' => 'Çek Test',
            'email' => 'cek-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);
        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);
    }

    private function cariOlustur(Firma $firma, string $kod): Cari
    {
        return Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => $kod,
            'ad' => 'Cari '.$kod,
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);
    }

    public function test_cek_girisi_idempotenttir_ve_kasa_hareketi_olusturmaz(): void
    {
        $firma = $this->firmaOlustur('CK1');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, 'C-1');
        $veri = [
            'cari_id' => $cari->id,
            'cek_no' => 'CHK-001',
            'tutar' => '1250.00',
            'para_birimi' => 'TRY',
            'vade_tarihi' => '2026-08-15',
            'islem_tarihi' => '2026-07-20 10:00:00',
        ];
        Storage::disk('public')->put('muhasebe/cekler/'.$firma->id.'/on-001.jpg', 'test');
        Storage::disk('public')->put('muhasebe/cekler/'.$firma->id.'/arka-001.jpg', 'test');
        $veri['on_gorsel_yolu'] = 'muhasebe/cekler/'.$firma->id.'/on-001.jpg';
        $veri['arka_gorsel_yolu'] = 'muhasebe/cekler/'.$firma->id.'/arka-001.jpg';

        $ilk = app(CekServisi::class)->girisKaydet($firma->id, $veri);
        $ikinci = app(CekServisi::class)->girisKaydet($firma->id, $veri);

        $this->assertSame($ilk->id, $ikinci->id);
        $this->assertDatabaseCount('cekler', 1);
        $this->assertDatabaseCount('cek_hareketleri', 1);
        $this->assertDatabaseCount('kasa_hareketleri', 0);
        $this->assertDatabaseCount('banka_hareketleri', 0);
        $this->assertDatabaseCount('pos_hareketleri', 0);
        $this->assertDatabaseHas('finans_hareketleri', [
            'referans_turu' => 'cek',
            'referans_id' => $ilk->id,
            'tur' => 'tahsilat',
        ]);
        $this->assertDatabaseHas('cekler', [
            'id' => $ilk->id,
            'on_gorsel_yolu' => 'muhasebe/cekler/'.$firma->id.'/on-001.jpg',
            'arka_gorsel_yolu' => 'muhasebe/cekler/'.$firma->id.'/arka-001.jpg',
        ]);
    }

    public function test_portfoydeki_cek_cariye_verilebilir(): void
    {
        $firma = $this->firmaOlustur('CK2');
        $this->superAdminVeSession($firma);
        $veren = $this->cariOlustur($firma, 'C-VEREN');
        $hedef = $this->cariOlustur($firma, 'C-HEDEF');
        $servis = app(CekServisi::class);
        $cek = $servis->girisKaydet($firma->id, [
            'cari_id' => $veren->id,
            'cek_no' => 'CHK-002',
            'tutar' => '850.00',
            'para_birimi' => 'TRY',
            'vade_tarihi' => '2026-08-20',
            'islem_tarihi' => '2026-07-20 10:00:00',
        ]);

        $servis->cikisKaydet($firma->id, [
            'kaynak' => 'portfoy',
            'cek_id' => $cek->id,
            'cari_id' => $hedef->id,
            'islem_tarihi' => '2026-07-20 11:00:00',
        ]);

        $this->assertSame(CekDurumu::Verildi, $cek->fresh()->durum);
        $this->assertSame(2, CekHareketi::query()->where('cek_id', $cek->id)->count());
        $this->assertDatabaseHas('finans_hareketleri', [
            'referans_turu' => 'cek',
            'referans_id' => $cek->id,
            'tur' => 'odeme',
            'cari_id' => $hedef->id,
        ]);
    }

    public function test_cek_iptali_finans_ve_cariyi_ters_kayitla_kapatir(): void
    {
        $firma = $this->firmaOlustur('CK3');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, 'C-3');
        $cek = app(CekServisi::class)->girisKaydet($firma->id, [
            'cari_id' => $cari->id,
            'cek_no' => 'CHK-003',
            'tutar' => '300.00',
            'para_birimi' => 'TRY',
            'vade_tarihi' => '2026-08-25',
            'islem_tarihi' => '2026-07-20 12:00:00',
        ]);
        $finansId = (int) CekHareketi::query()->where('cek_id', $cek->id)->value('finans_hareket_id');

        app(CekServisi::class)->iptalEt($cek);

        $this->assertSame(CekDurumu::Iptal, $cek->fresh()->durum);
        $this->assertSame(CekHareketDurumu::Iptal, CekHareketi::query()->where('cek_id', $cek->id)->firstOrFail()->durum);
        $this->assertSame('iptal', (string) FinansHareketi::query()->findOrFail($finansId)->getRawOriginal('durum'));
        $this->assertDatabaseHas('finans_hareketleri', [
            'iptal_edilen_hareket_id' => $finansId,
            'durum' => 'aktif',
        ]);
    }

    public function test_cek_hareketi_iptal_ve_duzelt_eski_finansa_baglanir(): void
    {
        $firma = $this->firmaOlustur('CK6');
        $this->superAdminVeSession($firma);
        $cari = $this->cariOlustur($firma, 'C-6');
        $servis = app(CekServisi::class);
        $cek = $servis->girisKaydet($firma->id, [
            'cari_id' => $cari->id,
            'cek_no' => 'CHK-006',
            'tutar' => '300.00',
            'para_birimi' => 'TRY',
            'vade_tarihi' => '2026-08-25',
            'islem_tarihi' => '2026-07-20 12:00:00',
        ]);
        $eskiFinansId = (int) CekHareketi::query()->where('cek_id', $cek->id)->value('finans_hareket_id');

        $yeni = $servis->hareketIptalEtVeDuzelt($cek, [
            'tutar' => '325.00',
            'islem_tarihi' => '2026-07-21 12:00:00',
            'aciklama' => 'Tutar düzeltmesi',
        ]);
        $yeniFinansId = (int) CekHareketi::query()->where('cek_id', $yeni->id)->value('finans_hareket_id');

        $this->assertNotSame($cek->id, $yeni->id);
        $this->assertSame('iptal', (string) FinansHareketi::query()->findOrFail($eskiFinansId)->getRawOriginal('durum'));
        $this->assertDatabaseHas('finans_hareketleri', [
            'id' => $yeniFinansId,
            'duzeltme_kaynagi_id' => $eskiFinansId,
            'durum' => 'aktif',
        ]);
    }

    public function test_normal_kullanici_baska_firmaya_cek_yazamaz(): void
    {
        $firma = $this->firmaOlustur('CK4');
        $baskaFirma = $this->firmaOlustur('CK5');
        $user = User::query()->create([
            'name' => 'Kiracı',
            'email' => 'kiraci-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => false,
        ]);
        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);
        $cari = $this->cariOlustur($baskaFirma, 'C-BAŞKA');

        $this->expectException(IsKuraliIstisnasi::class);
        app(CekServisi::class)->girisKaydet($baskaFirma->id, [
            'cari_id' => $cari->id,
            'cek_no' => 'CHK-004',
            'tutar' => '100.00',
            'para_birimi' => 'TRY',
            'vade_tarihi' => '2026-08-25',
            'islem_tarihi' => '2026-07-20 12:00:00',
        ]);
    }
}
