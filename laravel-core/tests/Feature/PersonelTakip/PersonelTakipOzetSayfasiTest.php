<?php

namespace Tests\Feature\PersonelTakip;

use App\Filament\Clusters\PersonelTakip\Pages\PersonelTakipOzetSayfasi;
use App\Models\Firma;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelAvansi;
use App\Models\Personel\PersonelBelgesi;
use App\Models\Personel\PersonelGirisCikisi;
use App\Models\Personel\PersonelIzni;
use App\Models\Personel\PersonelMaasDonemi;
use App\Models\Personel\PersonelVardiyasi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonelTakipOzetSayfasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ozet_kpi_degerleri_aktif_firma_ile_sinirlanir(): void
    {
        $firma = $this->firmaOlustur('POZ');
        $digerFirma = $this->firmaOlustur('POD');
        app(TenantContextService::class)->firmaAyarla($firma);

        $personel = $this->personelOlustur($firma, 'Özet Personeli');
        $this->personelOlustur($digerFirma, 'Diğer Firma Personeli');

        PersonelVardiyasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'tarih' => now()->toDateString(),
            'baslangic_at' => now()->setTime(9, 0),
            'bitis_at' => now()->setTime(18, 0),
            'durum' => 'planlandi',
        ]);
        PersonelGirisCikisi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'giris_at' => now()->setTime(9, 5),
            'onay_durumu' => 'onay_bekliyor',
        ]);
        PersonelIzni::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'izin_turu' => 'mazeret',
            'baslangic_at' => now()->addDay()->setTime(9, 0),
            'bitis_at' => now()->addDay()->setTime(18, 0),
            'durum' => 'onay_bekliyor',
            'onay_durumu' => 'onay_bekliyor',
        ]);
        PersonelAvansi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'tarih' => now()->toDateString(),
            'tutar' => 750,
            'kalan_tutar' => 750,
            'durum' => 'onaylandi',
            'onay_durumu' => 'bekliyor',
        ]);
        PersonelMaasDonemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'baslangic_tarihi' => now()->startOfMonth()->toDateString(),
            'bitis_tarihi' => now()->endOfMonth()->toDateString(),
            'durum' => 'hesaplandi',
        ]);
        PersonelBelgesi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'belge_turu' => 'saglik_raporu',
            'ad' => 'Sağlık raporu',
            'dosya_yolu' => 'personel/belgeleri/saglik.pdf',
            'uyari_tarihi' => now()->subDay()->toDateString(),
            'gecerlilik_tarihi' => now()->addMonth()->toDateString(),
        ]);

        $kpi = app(PersonelTakipOzetSayfasi::class)->kpi();

        $this->assertSame(1, $kpi['aktif_personel']);
        $this->assertSame(1, $kpi['bugun_vardiya']);
        $this->assertSame(1, $kpi['acik_giris']);
        $this->assertSame(1, $kpi['bekleyen_izin']);
        $this->assertSame(1, $kpi['bekleyen_avans']);
        $this->assertSame(750.0, $kpi['acik_avans_tutari']);
        $this->assertSame(1, $kpi['acik_maas_donemi']);
        $this->assertSame(1, $kpi['yenilenecek_belge']);
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

    private function personelOlustur(Firma $firma, string $ad): Personel
    {
        return Personel::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad_soyad' => $ad,
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 10000,
            'durum' => Personel::DURUM_AKTIF,
        ]);
    }
}
