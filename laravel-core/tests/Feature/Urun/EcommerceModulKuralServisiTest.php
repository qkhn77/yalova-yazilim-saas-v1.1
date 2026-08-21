<?php

namespace Tests\Feature\Urun;

use App\Models\Firma;
use App\Models\FirmaModulu;
use App\Models\Modul;
use App\Services\EcommerceFirmaAyarServisi;
use App\Services\EcommerceModulKuralServisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EcommerceModulKuralServisiTest extends TestCase
{
    use RefreshDatabase;

    private function firmaOlustur(string $prefix): Firma
    {
        return Firma::query()->create([
            'ad' => 'ET '.$prefix,
            'kisa_ad' => $prefix,
            'firma_kodu' => $prefix.'-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
    }

    private function modulBagla(Firma $firma): void
    {
        $modul = Modul::query()->firstOrCreate(
            ['kod' => 'e_ticaret'],
            ['ad' => 'E-ticaret', 'aktif_mi' => true, 'siralama' => 50],
        );
        if (! $modul->aktif_mi) {
            $modul->update(['aktif_mi' => true]);
        }

        FirmaModulu::query()->updateOrCreate(
            ['firma_id' => $firma->id, 'modul_id' => $modul->id],
            ['durum' => 'aktif'],
        );
    }

    public function test_erisim_acik_mi_modul_ve_firma_ayarina_gore_hesaplanir(): void
    {
        $firma = $this->firmaOlustur('KURAL');
        $this->modulBagla($firma);

        app(EcommerceFirmaAyarServisi::class)->kaydetAyarlar((int) $firma->id, [
            'ecommerce_etkin_mi' => false,
        ]);

        $servis = app(EcommerceModulKuralServisi::class);
        $this->assertFalse($servis->erisimAcikMi((int) $firma->id));

        app(EcommerceFirmaAyarServisi::class)->kaydetAyarlar((int) $firma->id, [
            'ecommerce_etkin_mi' => true,
        ]);

        $this->assertTrue($servis->erisimAcikMi((int) $firma->id));
    }

    public function test_kapali_mod_erisim_logu_audit_tablosuna_yazilir(): void
    {
        if (! Schema::hasTable('denetim_kayitlari')) {
            $this->markTestSkipped('denetim_kayitlari tablosu yok.');
        }

        $firma = $this->firmaOlustur('AUDIT');
        $servis = app(EcommerceModulKuralServisi::class);
        $istek = Request::create('/sepet', 'GET');

        $servis->engelliErisimiKaydet($istek, (int) $firma->id);

        $this->assertDatabaseHas('denetim_kayitlari', [
            'olay' => 'ecommerce.kapali_modul_erisim_engellendi',
            'firma_id' => (int) $firma->id,
        ]);
    }
}

