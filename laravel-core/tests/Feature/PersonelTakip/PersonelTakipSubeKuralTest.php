<?php

namespace Tests\Feature\PersonelTakip;

use App\Models\Firma;
use App\Models\Sube;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PersonelTakipSubeKuralTest extends TestCase
{
    use RefreshDatabase;

    public function test_sube_kodu_ayni_firmada_tekrarlanamaz(): void
    {
        $firma = $this->firmaOlustur('SBK');

        Sube::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Merkez',
            'kod' => 'MRK',
            'aktif_mi' => true,
        ]);

        $this->expectException(ValidationException::class);

        Sube::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'ad' => 'Merkez Kopya',
            'kod' => 'MRK',
            'aktif_mi' => true,
        ]);
    }

    public function test_sube_kodu_firma_bazli_izole_edilir(): void
    {
        $firmaA = $this->firmaOlustur('SBA');
        $firmaB = $this->firmaOlustur('SBB');

        Sube::withoutGlobalScopes()->create([
            'firma_id' => $firmaA->id,
            'ad' => 'Merkez A',
            'kod' => 'MRK',
            'aktif_mi' => true,
        ]);
        $subeB = Sube::withoutGlobalScopes()->create([
            'firma_id' => $firmaB->id,
            'ad' => 'Merkez B',
            'kod' => 'MRK',
            'aktif_mi' => true,
        ]);

        $this->assertSame($firmaB->id, $subeB->firma_id);
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
