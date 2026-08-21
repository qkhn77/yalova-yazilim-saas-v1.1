<?php

namespace Tests\Feature\Urun\Concerns;

use App\Models\Ecommerce\EcommerceKargoYontemi;
use App\Models\Firma;
use App\Services\FirmaAyarDeposu;

trait CheckoutTestVerileri
{
    protected function checkoutTestVarsayilanlariniOlustur(Firma $firma): void
    {
        app(FirmaAyarDeposu::class)->yaz((int) $firma->id, 'ecommerce_odeme_aktif_mi', true);
        app(FirmaAyarDeposu::class)->yaz((int) $firma->id, 'ecommerce_odeme_provider', 'paytr');
        app(FirmaAyarDeposu::class)->yaz((int) $firma->id, 'test_modu', true);

        $this->checkoutTestKargoYontemiOlustur($firma);
    }

    /**
     * @param  array<string, mixed>  $ek
     * @return array<string, mixed>
     */
    protected function checkoutTestVerisi(Firma|int|null $firma = null, array $ek = []): array
    {
        if (! $firma instanceof Firma) {
            $firma = $firma !== null
                ? Firma::query()->findOrFail((int) $firma)
                : Firma::query()->latest('id')->firstOrFail();
        }

        $kargo = $this->checkoutTestKargoYontemiOlustur($firma);

        return array_merge([
            'musteri_ad_soyad' => 'Test User',
            'musteri_telefon' => '05001112233',
            'musteri_email' => 't@example.com',
            'teslimat_ulke' => 'TR',
            'teslimat_il' => 'Yalova',
            'teslimat_ilce' => 'Merkez',
            'teslimat_posta_kodu' => '77000',
            'teslimat_adresi' => 'Yalova Merkez test teslimat adresi',
            'kargo_yontemi_id' => $kargo->id,
            'odeme_yontemi_secimi' => 'online_kart',
        ], $ek);
    }

    protected function checkoutTestKargoYontemiOlustur(Firma $firma): EcommerceKargoYontemi
    {
        return EcommerceKargoYontemi::query()->updateOrCreate(
            [
                'firma_id' => $firma->id,
                'kod' => 'test-standart-kargo',
            ],
            [
                'ad' => 'Test Standart Kargo',
                'tip' => 'sabit',
                'hizmet_tipi' => 'standart',
                'aktif_mi' => true,
                'yurt_ici_aktif' => true,
                'yurt_disi_aktif' => false,
                'para_birimi' => 'TRY',
                'sabit_ucret' => 0,
                'ucretsiz_esik' => null,
                'tahmini_teslim_gun' => 1,
                'sira' => 1,
                'entegrasyon_aktif' => false,
                'entegrasyon' => 'test',
                'entegrasyon_ayarlar' => [],
                'kural' => [],
                'bolge_kurali' => [
                    'ulke_kapsami' => 'domestic_only',
                    'ulkeler' => 'TR',
                    'iller' => '',
                ],
                'iade_kargo_aktif' => false,
                'iade_kargo_ayarlar' => [],
            ]
        );
    }
}
