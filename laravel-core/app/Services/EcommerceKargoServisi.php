<?php

namespace App\Services;

use App\Models\Ecommerce\EcommerceKargoYontemi;
use App\Models\Ecommerce\Sepet;
use App\Services\Front\FrontFiyatServisi;
use Illuminate\Support\Collection;

class EcommerceKargoServisi
{
    public function __construct(
        private readonly FrontFiyatServisi $frontFiyatServisi,
    ) {}

    /**
     * @param  array<string, mixed>  $adres
     * @return Collection<int, array{yontem:EcommerceKargoYontemi,ucret:float,ucret_formatli:string,ucretsiz_mi:bool,tahmini_teslim:string}>
     */
    public function checkoutSecenekleri(int $firmaId, Sepet $sepet, array $toplamlar, array $adres = []): Collection
    {
        $siparisTutari = (float) ($toplamlar['genel_toplam'] ?? 0);
        $siparisParaBirimi = strtoupper((string) ($toplamlar['para_birimi'] ?? $this->frontFiyatServisi->aktifParaBirimi()));
        $desi = $this->sepetDesiTahmini($sepet);

        return $this->uygunSecenekleri($firmaId, $siparisTutari, $desi, $adres, $siparisParaBirimi);
    }

    /**
     * @param  array<string, mixed>  $veri
     * @return Collection<int, array{yontem:EcommerceKargoYontemi,ucret:float,ucret_formatli:string,ucretsiz_mi:bool,tahmini_teslim:string,kapsam_ozeti:string}>
     */
    public function simulasyonSecenekleri(int $firmaId, array $veri): Collection
    {
        return $this->uygunSecenekleri(
            $firmaId,
            (float) ($veri['siparis_tutari'] ?? 0),
            max(0.0, (float) ($veri['desi'] ?? 0)),
            [
                'teslimat_ulke' => $veri['teslimat_ulke'] ?? 'TR',
                'teslimat_il' => $veri['teslimat_il'] ?? '',
                'teslimat_posta_kodu' => $veri['teslimat_posta_kodu'] ?? '',
            ],
            'TRY'
        );
    }

    public function seciliYontemUcreti(EcommerceKargoYontemi $yontem, Sepet $sepet, array $toplamlar, array $adres = []): float
    {
        $ulke = $this->ulkeKodu($adres['teslimat_ulke'] ?? 'TR');
        $il = trim((string) ($adres['teslimat_il'] ?? ''));
        $postaKodu = trim((string) ($adres['teslimat_posta_kodu'] ?? ''));
        $siparisParaBirimi = strtoupper((string) ($toplamlar['para_birimi'] ?? $this->frontFiyatServisi->aktifParaBirimi()));
        $yontemParaBirimi = strtoupper((string) ($yontem->para_birimi ?: 'TRY'));
        if (! $this->frontFiyatServisi->cevrilebilirMi($siparisParaBirimi, $yontemParaBirimi)) {
            return -1;
        }

        $siparisTutari = $this->frontFiyatServisi->cevir((float) ($toplamlar['genel_toplam'] ?? 0), $siparisParaBirimi, $yontemParaBirimi);
        $desi = $this->sepetDesiTahmini($sepet);

        if (! $this->adresIcinUygunMu($yontem, $ulke, $il, $postaKodu, $siparisTutari, $desi)) {
            return -1;
        }

        return $this->ucretHesapla($yontem, $siparisTutari, $desi);
    }

    /**
     * @param  array<string, mixed>  $adres
     * @return Collection<int, array{yontem:EcommerceKargoYontemi,ucret:float,ucret_formatli:string,ucretsiz_mi:bool,tahmini_teslim:string,kapsam_ozeti:string}>
     */
    private function uygunSecenekleri(int $firmaId, float $siparisTutari, float $desi, array $adres, string $siparisParaBirimi): Collection
    {
        $ulke = $this->ulkeKodu($adres['teslimat_ulke'] ?? 'TR');
        $il = trim((string) ($adres['teslimat_il'] ?? ''));
        $postaKodu = trim((string) ($adres['teslimat_posta_kodu'] ?? ''));

        return EcommerceKargoYontemi::query()
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->orderBy('sira')
            ->orderBy('ad')
            ->get()
            ->filter(function (EcommerceKargoYontemi $yontem) use ($ulke, $il, $postaKodu, $siparisTutari, $siparisParaBirimi, $desi): bool {
                $yontemParaBirimi = strtoupper((string) ($yontem->para_birimi ?: 'TRY'));
                if (! $this->frontFiyatServisi->cevrilebilirMi($siparisParaBirimi, $yontemParaBirimi)) {
                    return false;
                }

                $yontemSiparisTutari = $this->frontFiyatServisi->cevir($siparisTutari, $siparisParaBirimi, $yontemParaBirimi);

                return $this->adresIcinUygunMu($yontem, $ulke, $il, $postaKodu, $yontemSiparisTutari, $desi);
            })
            ->map(function (EcommerceKargoYontemi $yontem) use ($siparisTutari, $siparisParaBirimi, $desi): array {
                $yontemParaBirimi = strtoupper((string) ($yontem->para_birimi ?: 'TRY'));
                if (! $this->frontFiyatServisi->cevrilebilirMi($yontemParaBirimi, $siparisParaBirimi)) {
                    return [];
                }

                $yontemSiparisTutari = $this->frontFiyatServisi->cevir($siparisTutari, $siparisParaBirimi, $yontemParaBirimi);
                $kaynakUcret = $this->ucretHesapla($yontem, $yontemSiparisTutari, $desi);
                $ucret = $this->frontFiyatServisi->cevir($kaynakUcret, $yontemParaBirimi, $siparisParaBirimi);

                return [
                    'yontem' => $yontem,
                    'ucret' => $ucret,
                    'kaynak_ucret' => $kaynakUcret,
                    'kaynak_para_birimi' => $yontemParaBirimi,
                    'ucret_formatli' => $ucret <= 0 ? 'Ücretsiz' : $this->frontFiyatServisi->formatla($ucret, $siparisParaBirimi),
                    'ucretsiz_mi' => $ucret <= 0,
                    'tahmini_teslim' => $this->tahminiTeslimMetni($yontem),
                    'kapsam_ozeti' => $this->kapsamOzeti($yontem),
                ];
            })
            ->filter()
            ->values();
    }

    private function ucretHesapla(EcommerceKargoYontemi $yontem, float $siparisTutari, float $desi): float
    {
        $ucretsizEsik = (float) ($yontem->ucretsiz_esik ?? 0);
        if ($ucretsizEsik > 0 && $siparisTutari >= $ucretsizEsik) {
            return 0.0;
        }

        $tip = (string) ($yontem->tip ?: 'sabit');
        if ($tip === 'tutar') {
            return $this->aralikUcreti((array) data_get($yontem->kural, 'tutar', []), $siparisTutari, (float) ($yontem->sabit_ucret ?? 0));
        }

        if ($tip === 'desi') {
            return $this->aralikUcreti((array) data_get($yontem->kural, 'desi', []), $desi, (float) ($yontem->sabit_ucret ?? 0));
        }

        return round(max(0, (float) ($yontem->sabit_ucret ?? 0)), 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $araliklar
     */
    private function aralikUcreti(array $araliklar, float $deger, float $varsayilan): float
    {
        foreach ($araliklar as $aralik) {
            $min = (float) ($aralik['min'] ?? 0);
            $max = (float) ($aralik['max'] ?? 0);
            if ($deger >= $min && ($max <= 0 || $deger <= $max)) {
                return round(max(0, (float) ($aralik['ucret'] ?? 0)), 2);
            }
        }

        return round(max(0, $varsayilan), 2);
    }

    private function adresIcinUygunMu(EcommerceKargoYontemi $yontem, string $ulke, string $il, string $postaKodu, float $siparisTutari, float $desi): bool
    {
        $yurtDisiMi = $ulke !== 'TR';
        if ($yurtDisiMi && ! (bool) $yontem->yurt_disi_aktif) {
            return false;
        }

        if (! $yurtDisiMi && ! (bool) $yontem->yurt_ici_aktif) {
            return false;
        }

        if (! $this->ulkeKuraliUygunMu($yontem, $ulke, $yurtDisiMi)) {
            return false;
        }

        $iller = data_get($yontem->bolge_kurali, 'iller');
        if (! $yurtDisiMi && is_string($iller) && trim($iller) !== '') {
            $izinliIller = $this->ayirListe($iller, true);

            if ($izinliIller->isNotEmpty() && ! $izinliIller->contains(mb_strtolower($il, 'UTF-8'))) {
                return false;
            }
        }

        if (! $this->postaKoduKuraliUygunMu($yontem, $postaKodu)) {
            return false;
        }

        if (! $this->limitKuraliUygunMu($yontem, $siparisTutari, $desi)) {
            return false;
        }

        return true;
    }

    private function ulkeKuraliUygunMu(EcommerceKargoYontemi $yontem, string $ulke, bool $yurtDisiMi): bool
    {
        $kapsam = (string) data_get($yontem->bolge_kurali, 'ulke_kapsami', '');
        $ulkeler = $this->ayirListe(data_get($yontem->bolge_kurali, 'ulkeler', ''), false, true);
        $haricUlkeler = $this->ayirListe(data_get($yontem->bolge_kurali, 'haric_ulkeler', ''), false, true);

        return match ($kapsam) {
            'domestic_only' => ! $yurtDisiMi,
            'international_only' => $yurtDisiMi,
            'selected_countries' => $ulkeler->isEmpty() ? true : $ulkeler->contains($ulke),
            'all_countries_except' => ! $haricUlkeler->contains($ulke),
            default => true,
        };
    }

    private function postaKoduKuraliUygunMu(EcommerceKargoYontemi $yontem, string $postaKodu): bool
    {
        $kurallar = $this->ayirListe(data_get($yontem->bolge_kurali, 'posta_kodlari', ''), false);
        if ($kurallar->isEmpty() || $postaKodu === '') {
            return true;
        }

        foreach ($kurallar as $kural) {
            if ($this->postaKoduEslesir($postaKodu, $kural)) {
                return true;
            }
        }

        return false;
    }

    private function limitKuraliUygunMu(EcommerceKargoYontemi $yontem, float $siparisTutari, float $desi): bool
    {
        $minimumSiparisTutari = (float) data_get($yontem->kural, 'minimum_siparis_tutari', 0);
        if ($minimumSiparisTutari > 0 && $siparisTutari < $minimumSiparisTutari) {
            return false;
        }

        $maksimumSiparisTutari = (float) data_get($yontem->kural, 'maksimum_siparis_tutari', 0);
        if ($maksimumSiparisTutari > 0 && $siparisTutari > $maksimumSiparisTutari) {
            return false;
        }

        $minimumDesi = (float) data_get($yontem->kural, 'minimum_desi', 0);
        if ($minimumDesi > 0 && $desi < $minimumDesi) {
            return false;
        }

        $maksimumDesi = (float) data_get($yontem->kural, 'maksimum_desi', 0);
        if ($maksimumDesi > 0 && $desi > $maksimumDesi) {
            return false;
        }

        return true;
    }

    private function postaKoduEslesir(string $postaKodu, string $kural): bool
    {
        $normalPastaKodu = mb_strtoupper(trim($postaKodu), 'UTF-8');
        $normalKural = mb_strtoupper(trim($kural), 'UTF-8');

        if ($normalKural === '') {
            return false;
        }

        if (str_contains($normalKural, '*')) {
            $regex = '/^'.str_replace('\*', '.*', preg_quote($normalKural, '/')).'$/u';

            return (bool) preg_match($regex, $normalPastaKodu);
        }

        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $normalKural, $eslesme) === 1) {
            if (! ctype_digit($normalPastaKodu)) {
                return false;
            }

            $deger = (int) $normalPastaKodu;
            $min = (int) $eslesme[1];
            $max = (int) $eslesme[2];

            return $deger >= $min && $deger <= $max;
        }

        return $normalPastaKodu === $normalKural;
    }

    /**
     * @return Collection<int, string>
     */
    private function ayirListe(mixed $liste, bool $lowercase = false, bool $uppercase = false): Collection
    {
        $liste = $this->listeyiMetneCevir($liste);

        return collect(preg_split('/[,;\r\n]+/', $liste) ?: [])
            ->map(function (string $item) use ($lowercase, $uppercase): string {
                $item = trim($item);

                if ($uppercase) {
                    return mb_strtoupper($item, 'UTF-8');
                }

                if ($lowercase) {
                    return mb_strtolower($item, 'UTF-8');
                }

                return $item;
            })
            ->filter()
            ->values();
    }

    private function listeyiMetneCevir(mixed $liste): string
    {
        if (is_array($liste)) {
            return implode(',', array_map(
                static fn (mixed $item): string => trim((string) $item),
                array_filter($liste, static fn (mixed $item): bool => $item !== null && $item !== '')
            ));
        }

        return trim((string) $liste);
    }

    private function kapsamOzeti(EcommerceKargoYontemi $yontem): string
    {
        $parcalar = [];
        $ulkeKapsami = (string) data_get($yontem->bolge_kurali, 'ulke_kapsami', '');
        $ulkeler = $this->listeyiMetneCevir(data_get($yontem->bolge_kurali, 'ulkeler', ''));
        $haricUlkeler = $this->listeyiMetneCevir(data_get($yontem->bolge_kurali, 'haric_ulkeler', ''));
        $iller = $this->listeyiMetneCevir(data_get($yontem->bolge_kurali, 'iller', ''));
        $postaKodlari = $this->listeyiMetneCevir(data_get($yontem->bolge_kurali, 'posta_kodlari', ''));

        $parcalar[] = match ($ulkeKapsami) {
            'domestic_only' => 'Sadece yurt içi',
            'international_only' => 'Sadece yurt dışı',
            'selected_countries' => $ulkeler !== '' ? 'Ülkeler: '.$ulkeler : 'Seçili ülkeler',
            'all_countries_except' => $haricUlkeler !== '' ? 'Hariç ülkeler: '.$haricUlkeler : 'Hariç ülke kuralı',
            default => ($yontem->yurt_disi_aktif ? 'Yurt içi + yurt dışı' : 'Yurt içi'),
        };

        if ($iller !== '') {
            $parcalar[] = 'İller: '.$iller;
        }

        if ($postaKodlari !== '') {
            $parcalar[] = 'Posta: '.$postaKodlari;
        }

        return implode(' · ', array_filter($parcalar));
    }

    private function tahminiTeslimMetni(EcommerceKargoYontemi $yontem): string
    {
        $gun = (int) ($yontem->tahmini_teslim_gun ?? 0);
        if ($gun <= 0) {
            return 'Teslimat süresi adresinize göre hesaplanır.';
        }

        return $gun === 1 ? 'Tahmini 1 iş günü' : 'Tahmini '.$gun.' iş günü';
    }

    private function sepetDesiTahmini(Sepet $sepet): float
    {
        $sepet->loadMissing('kalemler.stokKarti');

        $desi = $sepet->kalemler->sum(function ($kalem): float {
            $miktar = max(0.0, (float) ($kalem->miktar ?? 0));
            $stok = $kalem->stokKarti;

            if (! $stok) {
                return $miktar;
            }

            $hacimM3 = max(0.0, (float) ($stok->hacim ?? 0));
            if ($hacimM3 > 0) {
                return $miktar * $hacimM3 * 333.33;
            }

            $agirlikKg = max(0.0, (float) ($stok->agirlik ?? 0));
            if ($agirlikKg > 0) {
                return $miktar * $agirlikKg;
            }

            return $miktar;
        });

        return round(max(1.0, (float) $desi), 2);
    }

    private function ulkeKodu(mixed $ulke): string
    {
        $kod = strtoupper(trim((string) $ulke));

        return $kod !== '' ? mb_substr($kod, 0, 2, 'UTF-8') : 'TR';
    }
}
