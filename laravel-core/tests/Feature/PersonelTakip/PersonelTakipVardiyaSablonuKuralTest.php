<?php

namespace Tests\Feature\PersonelTakip;

use App\Models\Firma;
use App\Models\Personel\PersonelVardiyaSablonu;
use App\Models\Sube;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PersonelTakipVardiyaSablonuKuralTest extends TestCase
{
    use RefreshDatabase;

    public function test_vardiya_sablonu_farkli_firmanin_subesine_baglanamaz(): void
    {
        $firmaA = $this->firmaOlustur('VSA');
        $firmaB = $this->firmaOlustur('VSB');
        $subeB = Sube::withoutGlobalScopes()->create([
            'firma_id' => $firmaB->id,
            'ad' => 'Başka Şube',
            'kod' => 'BSB',
            'aktif_mi' => true,
        ]);

        $this->expectException(ValidationException::class);

        PersonelVardiyaSablonu::withoutGlobalScopes()->create([
            'firma_id' => $firmaA->id,
            'sube_id' => $subeB->id,
            'ad' => 'Sabah',
            'baslangic_saati' => '08:00',
            'bitis_saati' => '16:00',
        ]);
    }

    public function test_vardiya_sablonu_adi_ayni_kapsamda_benzersizdir(): void
    {
        $firma = $this->firmaOlustur('VSC');

        PersonelVardiyaSablonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Sabah',
            'baslangic_saati' => '08:00',
            'bitis_saati' => '16:00',
        ]);

        $this->expectException(ValidationException::class);

        PersonelVardiyaSablonu::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Sabah',
            'baslangic_saati' => '09:00',
            'bitis_saati' => '17:00',
        ]);
    }

    public function test_vardiya_sablonu_firma_bazli_izole_edilir(): void
    {
        $firmaA = $this->firmaOlustur('VSD');
        $firmaB = $this->firmaOlustur('VSE');

        PersonelVardiyaSablonu::withoutGlobalScopes()->create([
            'firma_id' => $firmaA->id,
            'ad' => 'Sabah',
            'baslangic_saati' => '08:00',
            'bitis_saati' => '16:00',
        ]);
        PersonelVardiyaSablonu::withoutGlobalScopes()->create([
            'firma_id' => $firmaB->id,
            'ad' => 'Sabah',
            'baslangic_saati' => '08:00',
            'bitis_saati' => '16:00',
        ]);

        $this->assertSame(2, PersonelVardiyaSablonu::withoutGlobalScopes()->count());
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
}
