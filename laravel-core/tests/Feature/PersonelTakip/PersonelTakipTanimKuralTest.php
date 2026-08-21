<?php

namespace Tests\Feature\PersonelTakip;

use App\Models\Firma;
use App\Models\Personel\PersonelDepartmani;
use App\Models\Personel\PersonelGorevi;
use App\Models\Sube;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PersonelTakipTanimKuralTest extends TestCase
{
    use RefreshDatabase;

    public function test_departman_farkli_firmanin_subesine_baglanamaz(): void
    {
        $firmaA = $this->firmaOlustur('TDA');
        $firmaB = $this->firmaOlustur('TDB');
        $subeB = $this->subeOlustur($firmaB, 'Baska Sube', 'BSB');

        $this->expectException(ValidationException::class);

        PersonelDepartmani::withoutGlobalScopes()->create([
            'firma_id' => $firmaA->id,
            'sube_id' => $subeB->id,
            'ad' => 'Servis',
            'kod' => 'SRV',
            'aktif_mi' => true,
        ]);
    }

    public function test_departman_kodu_ayni_firmada_tekrarlanamaz(): void
    {
        $firma = $this->firmaOlustur('TDK');

        PersonelDepartmani::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Servis',
            'kod' => 'SRV',
            'aktif_mi' => true,
        ]);

        $this->expectException(ValidationException::class);

        PersonelDepartmani::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Servis Kopya',
            'kod' => 'SRV',
            'aktif_mi' => true,
        ]);
    }

    public function test_gorev_farkli_firmanin_departmanina_baglanamaz(): void
    {
        $firmaA = $this->firmaOlustur('TGA');
        $firmaB = $this->firmaOlustur('TGB');
        $departmanB = PersonelDepartmani::withoutGlobalScopes()->create([
            'firma_id' => $firmaB->id,
            'ad' => 'Baska Departman',
            'kod' => 'BDP',
            'aktif_mi' => true,
        ]);

        $this->expectException(ValidationException::class);

        PersonelGorevi::withoutGlobalScopes()->create([
            'firma_id' => $firmaA->id,
            'departman_id' => $departmanB->id,
            'ad' => 'Garson',
            'kod' => 'GAR',
            'aktif_mi' => true,
        ]);
    }

    public function test_gorev_kodu_ayni_firmada_tekrarlanamaz(): void
    {
        $firma = $this->firmaOlustur('TGK');

        PersonelGorevi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Garson',
            'kod' => 'GAR',
            'aktif_mi' => true,
        ]);

        $this->expectException(ValidationException::class);

        PersonelGorevi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Garson Kopya',
            'kod' => 'GAR',
            'aktif_mi' => true,
        ]);
    }

    public function test_gorev_varsayilan_ucreti_negatif_olamaz(): void
    {
        $firma = $this->firmaOlustur('TGN');

        $this->expectException(ValidationException::class);

        PersonelGorevi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Negatif Ucret',
            'kod' => 'NEG',
            'varsayilan_ucret' => -1,
            'aktif_mi' => true,
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
