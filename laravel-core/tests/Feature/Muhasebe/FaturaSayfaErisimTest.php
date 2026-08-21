<?php

namespace Tests\Feature\Muhasebe;

use App\Filament\Clusters\Muhasebe\Pages\GelenFatura;
use App\Filament\Clusters\Muhasebe\Pages\TumFaturalarSayfasi;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Models\Firma;
use App\Models\FirmaKullanici;
use App\Models\FirmaModulu;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\StokKarti;
use App\Models\Modul;
use App\Models\Rol;
use App\Models\User;
use App\Models\Yetki;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaturaSayfaErisimTest extends TestCase
{
    use RefreshDatabase;

    private function kullanici(Firma $firma, string $yetkiKodu): User
    {
        $modul = Modul::query()->firstOrCreate(['kod' => 'muhasebe'], ['ad' => 'Muhasebe', 'aktif_mi' => true, 'siralama' => 1]);
        FirmaModulu::query()->create(['firma_id' => $firma->id, 'modul_id' => $modul->id, 'durum' => 'aktif']);
        $yetki = Yetki::query()->create(['ad' => $yetkiKodu, 'kod' => $yetkiKodu, 'modul_kodu' => 'muhasebe', 'eylem' => 'guncelle']);
        $rol = Rol::query()->create(['ad' => 'R', 'kod' => 'r-'.uniqid(), 'sistem_rolu_mu' => false]);
        $rol->yetkiler()->attach($yetki->id);
        $user = User::query()->create(['name' => 'U', 'email' => uniqid().'@t.local', 'password' => bcrypt('x')]);
        FirmaKullanici::query()->withoutGlobalScopes()->create([
            'firma_id' => $firma->id, 'kullanici_id' => $user->id, 'rol_id' => $rol->id, 'durum' => 'aktif', 'varsayilan_firma_mi' => true,
        ]);

        return $user;
    }

    public function test_sadece_fatura_guncelle_ile_sayfa_ve_resource_erisimleri_tutarlidir(): void
    {
        $firma = Firma::query()->create(['ad' => 'F', 'kisa_ad' => 'F', 'firma_kodu' => 'F-'.uniqid(), 'durum' => Firma::DURUM_AKTIF, 'onaylandi_mi' => true]);
        $user = $this->kullanici($firma, MuhasebeYetkiSablonlari::FATURA_GUNCELLE);
        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);
        $this->assertTrue(TumFaturalarSayfasi::canAccess());
        $this->assertTrue(GelenFatura::canAccess());
        $this->assertTrue(FaturaKaynagi::canViewAny());
    }

    public function test_fatura_goruntuleme_sayfasi_varsayilan_detay_icerigi_gosterir(): void
    {
        $blade = file_get_contents(resource_path('views/filament/clusters/muhasebe/resources/fatura-kaynagi/pages/view-fatura.blade.php'));
        $sayfa = file_get_contents(app_path('Filament/Clusters/Muhasebe/Resources/FaturaKaynagi/Pages/ViewFatura.php'));

        $this->assertStringContainsString('{{ $this->infolist }}', $blade);
        $this->assertStringNotContainsString("request()->boolean('detay')", $blade);
        $this->assertStringContainsString("return ! request()->boolean('hizli');", $sayfa);
    }

    public function test_stok_ve_birim_arama_kisa_arama_ve_tenant_izolasyonunu_korur(): void
    {
        $firma = Firma::query()->create(['ad' => 'F1', 'kisa_ad' => 'F1', 'firma_kodu' => 'F-'.uniqid(), 'durum' => Firma::DURUM_AKTIF, 'onaylandi_mi' => true]);
        $digerFirma = Firma::query()->create(['ad' => 'F2', 'kisa_ad' => 'F2', 'firma_kodu' => 'F-'.uniqid(), 'durum' => Firma::DURUM_AKTIF, 'onaylandi_mi' => true]);
        $user = $this->kullanici($firma, MuhasebeYetkiSablonlari::FATURA_GUNCELLE);
        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $stok = StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'KAM-001',
            'ad' => 'Kamera Kablo',
            'tur' => StokKartiTuru::Diger->value,
            'durum' => 'aktif',
        ]);
        $digerStok = StokKarti::query()->create([
            'firma_id' => $digerFirma->id,
            'kod' => 'KAM-002',
            'ad' => 'Kamera Kablo Diger Firma',
            'tur' => StokKartiTuru::Diger->value,
            'durum' => 'aktif',
        ]);
        Birim::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'AD',
            'ad' => 'Adet',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);
        Birim::query()->create([
            'firma_id' => $digerFirma->id,
            'kod' => 'AD',
            'ad' => 'Adet Diger Firma',
            'aktif_mi' => true,
            'is_sabit' => false,
        ]);

        $stokSonuclari = FaturaKaynagi::stokAdAramaSonuclari('Kamera', (int) $firma->id);
        $birimSonuclari = FaturaKaynagi::birimAramaSonuclari('Adet', (int) $firma->id);
        $stokIlkSonuclari = FaturaKaynagi::stokAdAramaSonuclari('', (int) $firma->id);
        $birimIlkSonuclari = FaturaKaynagi::birimAramaSonuclari('', (int) $firma->id);

        $this->assertArrayHasKey((string) $stok->id, $stokSonuclari);
        $this->assertArrayNotHasKey((string) $digerStok->id, $stokSonuclari);
        $this->assertSame('Adet', $birimSonuclari['AD'] ?? null);
        $this->assertArrayHasKey((string) $stok->id, $stokIlkSonuclari);
        $this->assertSame('Adet', $birimIlkSonuclari['AD'] ?? null);
        $this->assertSame([], FaturaKaynagi::stokAdAramaSonuclari('K', (int) $firma->id));
        $this->assertSame([], FaturaKaynagi::birimAramaSonuclari('A', (int) $firma->id));
    }
}
