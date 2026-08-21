<?php

namespace App\Services;

use App\Models\Ecommerce\EcommercePazaryeriEntegrasyon;
use App\Services\Ecommerce\Pazaryeri\PazaryeriSiparisAdaptorFactory;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

class EcommercePazaryeriSiparisCekmeServisi
{
    public function __construct(
        private readonly SistemOlayServisi $sistemOlayServisi,
        private readonly PazaryeriSiparisAdaptorFactory $pazaryeriSiparisAdaptorFactory,
    ) {}

    /**
     * @return array<string, int>
     */
    public function calistir(?int $firmaId = null, ?string $pazaryeriKodu = null): array
    {
        $ozet = [
            'islenen' => 0,
            'basarili' => 0,
            'hatali' => 0,
            'atlanan' => 0,
            'import_edilen' => 0,
        ];

        $query = EcommercePazaryeriEntegrasyon::query()
            ->where('aktif_mi', true)
            ->where('siparis_cekme_aktif', true);

        if ($firmaId !== null && $firmaId > 0) {
            $query->where('firma_id', $firmaId);
        }

        if (is_string($pazaryeriKodu) && $pazaryeriKodu !== '') {
            $query->where('pazaryeri_kodu', $pazaryeriKodu);
        }

        $entegrasyonlar = $query->get();

        foreach ($entegrasyonlar as $entegrasyon) {
            if (! $this->islemeUygunMu($entegrasyon)) {
                $ozet['atlanan']++;

                continue;
            }

            $ozet['islenen']++;

            try {
                $importAdedi = $this->tekEntegrasyonCalistir($entegrasyon);
                $ozet['basarili']++;
                $ozet['import_edilen'] += $importAdedi;
            } catch (Throwable $e) {
                $this->hataKaydet($entegrasyon, $e);
                $ozet['hatali']++;
            }
        }

        return $ozet;
    }

    private function islemeUygunMu(EcommercePazaryeriEntegrasyon $entegrasyon): bool
    {
        $simdi = CarbonImmutable::now();
        $ayarlar = (array) ($entegrasyon->ayarlar ?? []);

        $periyotDakika = max(5, (int) ($entegrasyon->siparis_cekme_periyodu ?? 30));
        $sonSenkronAt = $entegrasyon->son_senkron_at;
        $periyotDoldu = $sonSenkronAt === null || $sonSenkronAt->copy()->addMinutes($periyotDakika)->lte($simdi);

        $retryNextAt = isset($ayarlar['retry_next_at'])
            ? CarbonImmutable::parse((string) $ayarlar['retry_next_at'])
            : null;

        $denemeSayisi = (int) ($ayarlar['deneme_sayisi'] ?? 0);
        $maxDeneme = max(1, (int) ($entegrasyon->max_deneme ?? 3));

        $retryZamaniGeldi = $retryNextAt !== null
            && $retryNextAt->lte($simdi)
            && $denemeSayisi > 0
            && $denemeSayisi < $maxDeneme;

        return $periyotDoldu || $retryZamaniGeldi;
    }

    private function tekEntegrasyonCalistir(EcommercePazaryeriEntegrasyon $entegrasyon): int
    {
        $this->entegrasyonKimlikKontrol($entegrasyon);

        $siparisler = $this->pazaryeriSiparisleriniGetir($entegrasyon);
        $importAdedi = count($siparisler);

        $ayarlar = (array) ($entegrasyon->ayarlar ?? []);
        $ayarlar['deneme_sayisi'] = 0;
        $ayarlar['retry_next_at'] = null;
        $ayarlar['son_hata'] = null;
        $ayarlar['son_basarili_at'] = now()->toIso8601String();
        $ayarlar['son_import_adedi'] = $importAdedi;

        $entegrasyon->update([
            'ayarlar' => $ayarlar,
            'son_senkron_at' => now(),
        ]);

        $this->sistemOlayServisi->olayKaydet(
            tip: 'pazaryeri.siparis_cekme.basarili',
            seviye: 'info',
            mesaj: 'Pazaryeri siparis cekimi basarili.',
            context: [
                'firma_id' => (int) $entegrasyon->firma_id,
                'pazaryeri' => (string) $entegrasyon->pazaryeri_kodu,
                'import_adedi' => $importAdedi,
            ]
        );

        return $importAdedi;
    }

    private function hataKaydet(EcommercePazaryeriEntegrasyon $entegrasyon, Throwable $e): void
    {
        $ayarlar = (array) ($entegrasyon->ayarlar ?? []);
        $maxDeneme = max(1, (int) ($entegrasyon->max_deneme ?? 3));
        $denemeSayisi = max(0, (int) ($ayarlar['deneme_sayisi'] ?? 0)) + 1;

        $yenidenDenemeDakika = 5;
        if ($denemeSayisi >= $maxDeneme) {
            $denemeSayisi = 0;
            $entegrasyon->son_senkron_at = now();
            $yenidenDenemeDakika = max(5, (int) ($entegrasyon->siparis_cekme_periyodu ?? 30));
        }

        $ayarlar['deneme_sayisi'] = $denemeSayisi;
        $ayarlar['retry_next_at'] = now()->addMinutes($yenidenDenemeDakika)->toIso8601String();
        $ayarlar['son_hata'] = mb_substr($e->getMessage(), 0, 500);
        $ayarlar['son_hata_at'] = now()->toIso8601String();

        $entegrasyon->ayarlar = $ayarlar;
        $entegrasyon->save();

        if ((bool) $entegrasyon->hata_uyari_aktif) {
            $this->sistemOlayServisi->olayKaydet(
                tip: 'pazaryeri.siparis_cekme.hata',
                seviye: $denemeSayisi === 0 ? 'error' : 'warning',
                mesaj: 'Pazaryeri siparis cekimi sirasinda hata olustu.',
                context: [
                    'firma_id' => (int) $entegrasyon->firma_id,
                    'pazaryeri' => (string) $entegrasyon->pazaryeri_kodu,
                    'hata' => mb_substr($e->getMessage(), 0, 500),
                    'retry_next_at' => $ayarlar['retry_next_at'],
                ]
            );
        }
    }

    private function entegrasyonKimlikKontrol(EcommercePazaryeriEntegrasyon $entegrasyon): void
    {
        $kimlik = (array) ($entegrasyon->kimlik_bilgileri ?? []);

        $apiKey = (string) ($kimlik['api_key'] ?? '');
        $apiSecret = (string) ($kimlik['api_secret'] ?? '');

        if ($apiKey === '' || $apiSecret === '') {
            throw new RuntimeException('Pazaryeri API kimlik bilgileri eksik.');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pazaryeriSiparisleriniGetir(EcommercePazaryeriEntegrasyon $entegrasyon): array
    {
        // Mock fallback: entegrasyon testi / dev ortaminda manuel deneme icin acik tutulur.
        $ayarlar = (array) ($entegrasyon->ayarlar ?? []);
        $mockSiparis = (int) ($ayarlar['mock_siparis_adedi'] ?? 0);

        if ($mockSiparis <= 0) {
            $adaptor = $this->pazaryeriSiparisAdaptorFactory->make((string) $entegrasyon->pazaryeri_kodu);

            return $adaptor->siparisleriGetir($entegrasyon);
        }

        $liste = [];
        for ($i = 1; $i <= $mockSiparis; $i++) {
            $liste[] = [
                'dis_siparis_no' => (string) $entegrasyon->pazaryeri_kodu.'-'.now()->format('YmdHis').'-'.$i,
                'toplam' => 0,
            ];
        }

        return $liste;
    }
}
