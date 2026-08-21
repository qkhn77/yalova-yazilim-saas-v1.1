<?php

namespace Tests\Feature;

use App\Models\Firma;
use App\Models\FirmaKullanici;
use App\Models\Iletisim\KullaniciMesajKatilimcisi;
use App\Models\User;
use App\Services\MesajMerkeziServisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MesajMerkeziServisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_okunmamis_mesaj_sayaci_hizli_sorgu_ve_cache_temizligiyle_guncellenir(): void
    {
        Cache::flush();

        $gonderen = User::factory()->create(['super_admin_mi' => true]);
        $alici = User::factory()->create();
        $servis = app(MesajMerkeziServisi::class);

        $konu = $servis->konuOlustur(
            $gonderen,
            null,
            [$alici->id],
            'Servis planlamasi',
            'Yarin icin ekip planini kontrol eder misin?',
        );

        $this->assertSame(1, $servis->sayaclar($alici)['okunmamis_mesaj']);
        $this->assertSame(0, $servis->sayaclar($gonderen)['okunmamis_mesaj']);

        $servis->okunduIsaretle($konu, $alici);

        $this->assertSame(0, $servis->sayaclar($alici)['okunmamis_mesaj']);
    }

    public function test_okundu_isaretle_tekrarlaninca_akis_cache_gereksiz_tazelenmez(): void
    {
        Cache::flush();

        $gonderen = User::factory()->create(['super_admin_mi' => true]);
        $alici = User::factory()->create();
        $servis = app(MesajMerkeziServisi::class);

        $konu = $servis->konuOlustur(
            $gonderen,
            null,
            [$alici->id],
            'Servis planlamasi',
            'Ilk okuma sonrasinda tekrar secim cache bozmasin.',
        );

        $servis->okunduIsaretle($konu, $alici);
        $akisSurumu = $servis->akisCacheSurumu($alici);

        $servis->okunduIsaretle($konu->refresh(), $alici);

        $this->assertSame(0, $servis->sayaclar($alici)['okunmamis_mesaj']);
        $this->assertSame($akisSurumu, $servis->akisCacheSurumu($alici));
    }

    public function test_konu_olustururken_sadece_secili_ve_izinli_alicilar_dogrulanir(): void
    {
        Cache::flush();

        $firma = Firma::query()->create([
            'ad' => 'Yalova Kamera',
            'firma_kodu' => 'YK-TEST',
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);

        $gonderen = User::factory()->create();
        $firmaIciAlici = User::factory()->create();
        $firmaDisiAlici = User::factory()->create();

        foreach ([$gonderen, $firmaIciAlici] as $kullanici) {
            FirmaKullanici::query()->withoutGlobalScopes()->create([
                'firma_id' => $firma->id,
                'kullanici_id' => $kullanici->id,
                'durum' => 'aktif',
            ]);
        }

        $konu = app(MesajMerkeziServisi::class)->konuOlustur(
            $gonderen,
            $firma->id,
            [$firmaIciAlici->id, $firmaDisiAlici->id, $gonderen->id],
            'Firma ici bilgilendirme',
            'Sadece ayni firmadaki kullaniciya gitmeli.',
        );

        $this->assertDatabaseHas('kullanici_mesaj_katilimcilari', [
            'konu_id' => $konu->id,
            'kullanici_id' => $firmaIciAlici->id,
        ]);

        $this->assertDatabaseMissing('kullanici_mesaj_katilimcilari', [
            'konu_id' => $konu->id,
            'kullanici_id' => $firmaDisiAlici->id,
        ]);

        $this->assertSame(1, app(MesajMerkeziServisi::class)->sayaclar($firmaIciAlici, $firma->id)['okunmamis_mesaj']);
    }

    public function test_sessizdeki_katilimci_yanit_sonrasi_sayac_ve_akis_cache_tazelenir(): void
    {
        Cache::flush();

        $gonderen = User::factory()->create(['super_admin_mi' => true]);
        $alici = User::factory()->create();
        $servis = app(MesajMerkeziServisi::class);

        $konu = $servis->konuOlustur(
            $gonderen,
            null,
            [$alici->id],
            'Servis planlamasi',
            'Ilk mesaj.',
        );

        $servis->okunduIsaretle($konu, $alici);

        KullaniciMesajKatilimcisi::query()
            ->where('konu_id', $konu->id)
            ->where('kullanici_id', $alici->id)
            ->update(['sessize_alindi_mi' => true]);

        $this->assertSame(0, $servis->sayaclar($alici)['okunmamis_mesaj']);

        $akisSurumu = $servis->akisCacheSurumu($alici);
        $this->travel(2)->seconds();

        $servis->yanitGonder($konu->refresh(), $gonderen, 'Sessizdeki kullanici yine okunmamis gormeli.');

        $this->assertSame(1, $servis->sayaclar($alici)['okunmamis_mesaj']);
        $this->assertGreaterThan($akisSurumu, $servis->akisCacheSurumu($alici));
    }

    public function test_konu_olustururken_gecerli_alici_yoksa_hata_doner(): void
    {
        Cache::flush();

        $gonderen = User::factory()->create(['super_admin_mi' => false]);
        $firmaDisiAlici = User::factory()->create();

        $this->expectException(ValidationException::class);

        app(MesajMerkeziServisi::class)->konuOlustur(
            $gonderen,
            999,
            [$firmaDisiAlici->id],
            'Gecersiz alici',
            'Bu mesaj icin gecerli alici yok.',
        );
    }

    public function test_kullanici_secenekleri_limit_ve_toplam_sayisi_ayri_hesaplanir(): void
    {
        Cache::flush();

        $gonderen = User::factory()->create(['super_admin_mi' => true]);
        User::factory()->count(40)->create(['name' => 'Destek Kullanici']);

        $servis = app(MesajMerkeziServisi::class);

        $this->assertCount(24, $servis->kullaniciSecenekleri($gonderen, null, '', 24));
        $this->assertSame(40, $servis->kullaniciSecenekSayisi($gonderen, null, ''));
        $this->assertCount(40, $servis->kullaniciSecenekleri($gonderen, null, 'Destek', 60));
    }
}
