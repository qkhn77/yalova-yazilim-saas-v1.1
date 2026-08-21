<?php

namespace Tests\Feature\PersonelTakip;

use App\Models\Firma;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelVardiyaSablonu;
use App\Models\Personel\PersonelVardiyasi;
use App\Models\Sube;
use App\Services\PersonelTakip\PersonelVardiyaPlanlamaServisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PersonelTakipVardiyaPlanlamaServisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sablondan_secili_gunlere_toplu_vardiya_olusturur(): void
    {
        $firma = $this->firmaOlustur('VPA');
        $personelA = $this->personelOlustur($firma, 'Plan Personel A');
        $personelB = $this->personelOlustur($firma, 'Plan Personel B');
        $sablon = $this->sablonOlustur($firma);

        $sonuc = app(PersonelVardiyaPlanlamaServisi::class)->sablondanAralikOlustur(
            firmaId: $firma->id,
            sablonId: $sablon->id,
            personelIds: [$personelA->id, $personelB->id],
            baslangicTarihi: '2026-06-01',
            bitisTarihi: '2026-06-07',
            gunler: [1, 3, 5],
        );

        $this->assertSame(6, $sonuc['olusan']);
        $this->assertSame(0, $sonuc['atlanan']);
        $this->assertSame(6, PersonelVardiyasi::withoutGlobalScopes()->where('firma_id', $firma->id)->count());
    }

    public function test_toplu_planlama_cakisan_vardiyayi_atlar(): void
    {
        $firma = $this->firmaOlustur('VPB');
        $personel = $this->personelOlustur($firma, 'Cakisan Plan Personeli');
        $sablon = $this->sablonOlustur($firma);

        PersonelVardiyasi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'vardiya_sablonu_id' => $sablon->id,
            'tarih' => '2026-06-01',
            'durum' => 'planlandi',
        ]);

        $sonuc = app(PersonelVardiyaPlanlamaServisi::class)->sablondanAralikOlustur(
            firmaId: $firma->id,
            sablonId: $sablon->id,
            personelIds: [$personel->id],
            baslangicTarihi: '2026-06-01',
            bitisTarihi: '2026-06-02',
        );

        $this->assertSame(1, $sonuc['olusan']);
        $this->assertSame(1, $sonuc['atlanan']);
        $this->assertCount(1, $sonuc['hatalar']);
    }

    public function test_farkli_firma_sablonu_ile_toplu_vardiya_olusturulamaz(): void
    {
        $firmaA = $this->firmaOlustur('VPC');
        $firmaB = $this->firmaOlustur('VPD');
        $personel = $this->personelOlustur($firmaA, 'Yanlis Plan Personeli');
        $sablonB = $this->sablonOlustur($firmaB);

        $this->expectException(ValidationException::class);

        app(PersonelVardiyaPlanlamaServisi::class)->sablondanAralikOlustur(
            firmaId: $firmaA->id,
            sablonId: $sablonB->id,
            personelIds: [$personel->id],
            baslangicTarihi: '2026-06-01',
            bitisTarihi: '2026-06-01',
        );
    }

    public function test_toplu_planlama_farkli_firmanin_subesine_yapilamaz(): void
    {
        $firmaA = $this->firmaOlustur('VPE');
        $firmaB = $this->firmaOlustur('VPF');
        $personel = $this->personelOlustur($firmaA, 'Şube Hatalı Plan Personeli');
        $sablon = $this->sablonOlustur($firmaA);
        $subeB = $this->subeOlustur($firmaB, 'Baska Sube', 'BSB');

        $this->expectException(ValidationException::class);

        app(PersonelVardiyaPlanlamaServisi::class)->sablondanAralikOlustur(
            firmaId: $firmaA->id,
            sablonId: $sablon->id,
            personelIds: [$personel->id],
            baslangicTarihi: '2026-06-01',
            bitisTarihi: '2026-06-01',
            subeId: $subeB->id,
        );
    }

    public function test_toplu_planlama_sablon_subesi_ile_secilen_sube_uyumlu_olmalidir(): void
    {
        $firma = $this->firmaOlustur('VPG');
        $personel = $this->personelOlustur($firma, 'Uyumsuz Şube Plan Personeli');
        $subeA = $this->subeOlustur($firma, 'Merkez', 'MRK');
        $subeB = $this->subeOlustur($firma, 'Şube B', 'SBB');
        $sablon = $this->sablonOlustur($firma, $subeA);

        $this->expectException(ValidationException::class);

        app(PersonelVardiyaPlanlamaServisi::class)->sablondanAralikOlustur(
            firmaId: $firma->id,
            sablonId: $sablon->id,
            personelIds: [$personel->id],
            baslangicTarihi: '2026-06-01',
            bitisTarihi: '2026-06-01',
            subeId: $subeB->id,
        );
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

    private function sablonOlustur(Firma $firma, ?Sube $sube = null): PersonelVardiyaSablonu
    {
        return PersonelVardiyaSablonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'sube_id' => $sube?->id,
            'ad' => 'Sabah',
            'baslangic_saati' => '08:00',
            'bitis_saati' => '16:00',
            'mola_dakika' => 30,
            'aktif_mi' => true,
        ]);
    }

    private function subeOlustur(Firma $firma, string $ad, string $kod): Sube
    {
        return Sube::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => $ad,
            'kod' => $kod,
            'aktif_mi' => true,
        ]);
    }
}
