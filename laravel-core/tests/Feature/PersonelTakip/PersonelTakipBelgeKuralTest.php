<?php

namespace Tests\Feature\PersonelTakip;

use App\Models\Firma;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelBelgesi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PersonelTakipBelgeKuralTest extends TestCase
{
    use RefreshDatabase;

    public function test_personel_belgesi_personelin_firmasini_otomatik_alir(): void
    {
        $firma = $this->firmaOlustur('PBA');
        $personel = $this->personelOlustur($firma);

        $belge = PersonelBelgesi::withoutGlobalScopes()->create([
            'personel_id' => $personel->id,
            'belge_turu' => 'kimlik',
            'ad' => 'Kimlik',
            'dosya_yolu' => 'personel/belgeleri/kimlik.pdf',
        ]);

        $this->assertSame($firma->id, $belge->firma_id);
        $this->assertSame(1, PersonelBelgesi::withoutGlobalScopes()->where('personel_id', $personel->id)->count());
    }

    public function test_personel_belgesi_farkli_firma_personeline_baglanamaz(): void
    {
        $firmaA = $this->firmaOlustur('PBB');
        $firmaB = $this->firmaOlustur('PBC');
        $personelB = $this->personelOlustur($firmaB);

        $this->expectException(ValidationException::class);

        PersonelBelgesi::withoutGlobalScopes()->create([
            'firma_id' => $firmaA->id,
            'personel_id' => $personelB->id,
            'belge_turu' => 'sozlesme',
            'ad' => 'Sozlesme',
            'dosya_yolu' => 'personel/belgeleri/sozlesme.pdf',
        ]);
    }

    public function test_personel_belgesi_dosya_yolu_olmadan_olusturulamaz(): void
    {
        $firma = $this->firmaOlustur('PBD');
        $personel = $this->personelOlustur($firma);

        $this->expectException(ValidationException::class);

        PersonelBelgesi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'belge_turu' => 'diger',
            'ad' => 'Eksik Belge',
        ]);
    }

    public function test_personel_belgesi_gecerlilik_durumunu_hesaplar(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $firma = $this->firmaOlustur('PBE');
        $personel = $this->personelOlustur($firma);

        $belge = PersonelBelgesi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'belge_turu' => 'saglik_raporu',
            'ad' => 'Sağlık raporu',
            'dosya_yolu' => 'personel/belgeleri/saglik.pdf',
            'duzenleme_tarihi' => '2026-01-01',
            'uyari_tarihi' => '2026-05-15',
            'gecerlilik_tarihi' => '2026-06-30',
        ]);

        $this->assertSame('yenilenecek', $belge->durum);

        Carbon::setTestNow();
    }

    public function test_personel_belgesi_gecerlilik_tarihi_duzenleme_tarihinden_once_olamaz(): void
    {
        $firma = $this->firmaOlustur('PBF');
        $personel = $this->personelOlustur($firma);

        $this->expectException(ValidationException::class);

        PersonelBelgesi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'personel_id' => $personel->id,
            'belge_turu' => 'sozlesme',
            'ad' => 'Sözleşme',
            'dosya_yolu' => 'personel/belgeleri/sozlesme.pdf',
            'duzenleme_tarihi' => '2026-06-01',
            'gecerlilik_tarihi' => '2026-05-31',
        ]);
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
            'ad_soyad' => 'Belgeli Personel',
            'calisma_tipi' => 'tam_zamanli',
            'maas_tipi' => 'aylik',
            'maas_tutari' => 10000,
            'durum' => Personel::DURUM_AKTIF,
        ]);
    }
}
