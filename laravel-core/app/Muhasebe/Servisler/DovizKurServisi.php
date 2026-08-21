<?php

namespace App\Muhasebe\Servisler;

use App\Models\Firma;
use App\Models\Muhasebe\DovizKuru;
use App\Models\Muhasebe\ParaBirimi;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class DovizKurServisi
{
    /** @var array<string, array<string, string>|null> */
    private static array $tcmbGunlukKurCache = [];

    /**
     * @return array{kur:string,tarih:string,kaynak:string,aciklama:string}
     */
    public function otomatikKurGetir(string $kaynakParaBirimi, string $hedefParaBirimi, ?string $tarih = null): array
    {
        return $this->otomatikKurGetirKurTipi($kaynakParaBirimi, $hedefParaBirimi, $tarih, null);
    }

    /**
     * @return array{kur:string,tarih:string,kaynak:string,aciklama:string}
     */
    public function otomatikKurGetirKurTipi(
        string $kaynakParaBirimi,
        string $hedefParaBirimi,
        ?string $tarih = null,
        ?string $kurTipi = null
    ): array
    {
        $kaynak = strtoupper(trim($kaynakParaBirimi));
        $hedef = strtoupper(trim($hedefParaBirimi));
        $gun = $tarih ? Carbon::parse($tarih)->startOfDay() : now()->startOfDay();

        if ($kaynak === $hedef) {
            return [
                'kur' => '1.00000000',
                'tarih' => $gun->toDateString(),
                'kaynak' => 'Sistem',
                'aciklama' => 'Ayni para birimi icin kur 1 olarak ayarlandi.',
            ];
        }

        $tcmb = $this->tcmbPariteGetir($kaynak, $hedef, $gun, $kurTipi);
        $kurTipiEtiketi = strtoupper((string) ($tcmb['kur_tipi'] ?? 'OTOMATIK'));

        return [
            'kur' => $tcmb['kur'],
            'tarih' => $tcmb['tarih'],
            'kaynak' => 'TCMB',
            'aciklama' => 'TCMB otomatik kuru kullanildi ('.$kurTipiEtiketi.').',
        ];
    }

    /**
     * @return array{kur:string,tarih:string}
     */
    public function kurKaydet(
        int $firmaId,
        string $kaynakParaBirimi,
        string $hedefParaBirimi,
        string $tarih,
        string $kur,
        bool $manuelMi = true,
        ?string $saglayici = null,
        ?string $aciklama = null,
        bool $manuelKaydiEz = false
    ): array {
        $kaynak = strtoupper(trim($kaynakParaBirimi));
        $hedef = strtoupper(trim($hedefParaBirimi));
        $tarihNorm = Carbon::parse($tarih)->toDateString();
        $kurNorm = number_format((float) $kur, 8, '.', '');

        if ((float) $kurNorm <= 0) {
            throw new IsKuraliIstisnasi('Kur sifirdan buyuk olmalidir.');
        }

        $saglayiciNorm = strtolower(trim((string) ($saglayici ?: ($manuelMi ? 'manuel' : 'tcmb'))));
        DovizKuru::tenantScopeOlmadan(function () use ($firmaId, $kaynak, $hedef, $tarihNorm, $kurNorm, $manuelMi, $saglayiciNorm, $aciklama, $manuelKaydiEz): void {
            $mevcut = DovizKuru::query()
                ->where('firma_id', $firmaId)
                ->where('kaynak_para_birimi', $kaynak)
                ->where('hedef_para_birimi', $hedef)
                ->whereDate('tarih', $tarihNorm)
                ->first();

            // Manuel kayit varsa otomatik akisin ezmesine izin verme.
            if ($mevcut && (bool) $mevcut->manuel_mi && ! $manuelMi && ! $manuelKaydiEz) {
                return;
            }

            DovizKuru::query()->updateOrCreate(
                [
                    'firma_id' => $firmaId,
                    'kaynak_para_birimi' => $kaynak,
                    'hedef_para_birimi' => $hedef,
                    'tarih' => $tarihNorm,
                ],
                [
                    'kur' => $kurNorm,
                    'saglayici' => $saglayiciNorm,
                    'manuel_mi' => $manuelMi,
                    'aciklama' => $aciklama,
                ]
            );
        });

        return ['kur' => $kurNorm, 'tarih' => $tarihNorm];
    }

    public function otomatikKurKaydet(
        int $firmaId,
        string $kaynakParaBirimi,
        string $hedefParaBirimi,
        ?string $tarih = null,
        bool $manuelKaydiEz = false
    ): array {
        $sonuc = $this->otomatikKurGetir($kaynakParaBirimi, $hedefParaBirimi, $tarih);

        return $this->kurKaydet(
            $firmaId,
            $kaynakParaBirimi,
            $hedefParaBirimi,
            $sonuc['tarih'],
            $sonuc['kur'],
            manuelMi: false,
            saglayici: 'tcmb',
            aciklama: $sonuc['kaynak'].' otomatik kuru',
            manuelKaydiEz: $manuelKaydiEz
        );
    }

    /**
     * @return array{ok:int,hata:int}
     */
    public function firmaIcinBazParitelereOtomatikKurYukle(int $firmaId, ?string $tarih = null, bool $manuelKaydiEz = false): array
    {
        $baz = $this->bazParaBirimi();
        $kodlar = $this->firmaAktifParaBirimleri($firmaId);

        $ok = 0;
        $hata = 0;
        foreach ($kodlar as $kod) {
            if ($kod === $baz) {
                continue;
            }
            try {
                $this->otomatikKurKaydet($firmaId, $kod, $baz, $tarih, $manuelKaydiEz);
                $this->otomatikKurKaydet($firmaId, $baz, $kod, $tarih, $manuelKaydiEz);
                $ok += 2;
            } catch (\Throwable) {
                $hata += 2;
            }
        }

        return ['ok' => $ok, 'hata' => $hata];
    }

    /**
     * @return array{ok:int,hata:int,gun:int}
     */
    public function firmaIcinTarihAraligindaBazParitelereOtomatikKurYukle(
        int $firmaId,
        string $baslangic,
        string $bitis,
        bool $manuelKaydiEz = false
    ): array {
        $bas = Carbon::parse($baslangic)->startOfDay();
        $son = Carbon::parse($bitis)->startOfDay();
        if ($bas->greaterThan($son)) {
            throw new IsKuraliIstisnasi('Baslangic tarihi bitisten buyuk olamaz.');
        }

        $ok = 0;
        $hata = 0;
        $gun = 0;
        $cursor = $bas->copy();
        while ($cursor->lessThanOrEqualTo($son)) {
            $r = $this->firmaIcinBazParitelereOtomatikKurYukle($firmaId, $cursor->toDateString(), $manuelKaydiEz);
            $ok += $r['ok'];
            $hata += $r['hata'];
            $gun++;
            $cursor->addDay();
        }

        return ['ok' => $ok, 'hata' => $hata, 'gun' => $gun];
    }

    /**
     * @return array{ok:int,hata:int}
     */
    public function tumFirmalarIcinBazParitelereOtomatikKurYukle(?string $tarih = null, bool $manuelKaydiEz = false): array
    {
        $ok = 0;
        $hata = 0;

        $firmaIdler = Firma::query()->pluck('id')->all();
        foreach ($firmaIdler as $firmaId) {
            $r = $this->firmaIcinBazParitelereOtomatikKurYukle((int) $firmaId, $tarih, $manuelKaydiEz);
            $ok += $r['ok'];
            $hata += $r['hata'];
        }

        return ['ok' => $ok, 'hata' => $hata];
    }

    /**
     * @return array{
     *   beklenen:int,
     *   mevcut:int,
     *   eksik:int,
     *   satirlar:array<int,array{tarih:string,kaynak:string,hedef:string}>
     * }
     */
    public function eksikKurRaporu(int $firmaId, string $baslangic, string $bitis, int $limit = 300): array
    {
        $bas = Carbon::parse($baslangic)->startOfDay();
        $son = Carbon::parse($bitis)->startOfDay();
        if ($bas->greaterThan($son)) {
            throw new IsKuraliIstisnasi('Baslangic tarihi bitisten buyuk olamaz.');
        }

        $baz = $this->bazParaBirimi();
        $kodlar = $this->firmaAktifParaBirimleri($firmaId);
        $pariteler = [];
        foreach ($kodlar as $kod) {
            if ($kod === $baz) {
                continue;
            }
            $pariteler[] = [$kod, $baz];
            $pariteler[] = [$baz, $kod];
        }

        if ($pariteler === []) {
            return ['beklenen' => 0, 'mevcut' => 0, 'eksik' => 0, 'satirlar' => []];
        }

        $kayitlar = DovizKuru::tenantScopeOlmadan(function () use ($firmaId, $bas, $son) {
            return DovizKuru::query()
                ->where('firma_id', $firmaId)
                ->whereDate('tarih', '>=', $bas->toDateString())
                ->whereDate('tarih', '<=', $son->toDateString())
                ->get(['tarih', 'kaynak_para_birimi', 'hedef_para_birimi']);
        });

        $mevcutSet = [];
        foreach ($kayitlar as $k) {
            $mevcutSet[$k->tarih->toDateString().'|'.strtoupper((string) $k->kaynak_para_birimi).'|'.strtoupper((string) $k->hedef_para_birimi)] = true;
        }

        $satirlar = [];
        $beklenen = 0;
        $mevcut = 0;
        $cursor = $bas->copy();
        while ($cursor->lessThanOrEqualTo($son)) {
            $gun = $cursor->toDateString();
            foreach ($pariteler as [$kaynak, $hedef]) {
                $beklenen++;
                $key = $gun.'|'.$kaynak.'|'.$hedef;
                if (isset($mevcutSet[$key])) {
                    $mevcut++;
                    continue;
                }
                if (count($satirlar) < $limit) {
                    $satirlar[] = ['tarih' => $gun, 'kaynak' => $kaynak, 'hedef' => $hedef];
                }
            }
            $cursor->addDay();
        }

        return [
            'beklenen' => $beklenen,
            'mevcut' => $mevcut,
            'eksik' => max(0, $beklenen - $mevcut),
            'satirlar' => $satirlar,
        ];
    }

    /**
     * @return array{kur:string,tarih:string}
     */
    private function tcmbPariteGetir(string $kaynak, string $hedef, Carbon $tarih, ?string $kurTipi = null): array
    {
        $degerTipi = $this->tcmbDegerTipiBelirle($kurTipi);
        $maxGeriGun = (int) config('muhasebe.doviz.tcmb_geri_git_gun_sayisi', 7);
        for ($i = 0; $i <= $maxGeriGun; $i++) {
            $gun = (clone $tarih)->subDays($i);
            $kurlarTry = $this->tcmbTryKurlariGetir($gun, $degerTipi);
            if ($kurlarTry === null) {
                continue;
            }

            $kaynakTry = $this->tryDegeri($kaynak, $kurlarTry);
            $hedefTry = $this->tryDegeri($hedef, $kurlarTry);
            if ($kaynakTry === null || $hedefTry === null) {
                continue;
            }
            if ((float) $hedefTry <= 0) {
                continue;
            }

            $kur = bcdiv($kaynakTry, $hedefTry, 8);

            return [
                'kur' => number_format((float) $kur, 8, '.', ''),
                'tarih' => $gun->toDateString(),
                'kur_tipi' => $degerTipi,
            ];
        }

        throw new IsKuraliIstisnasi($kaynak.' -> '.$hedef.' icin TCMB kuru bulunamadi.');
    }

    /**
     * @return array<string,string>|null
     */
    private function tcmbTryKurlariGetir(Carbon $tarih, ?string $degerTipi = null): ?array
    {
        $url = $this->tcmbUrl($tarih);
        $cacheKey = $url.'|'.($degerTipi ?: (string) config('muhasebe.doviz.tcmb_deger_tipi', 'ForexSelling'));

        if (array_key_exists($cacheKey, self::$tcmbGunlukKurCache)) {
            return self::$tcmbGunlukKurCache[$cacheKey];
        }

        $yanit = Http::timeout((int) config('muhasebe.doviz.timeout_saniye', 10))->get($url);
        if (! $yanit->successful()) {
            return self::$tcmbGunlukKurCache[$cacheKey] = null;
        }

        $xml = @simplexml_load_string((string) $yanit->body());
        if (! $xml) {
            return self::$tcmbGunlukKurCache[$cacheKey] = null;
        }

        $secim = $degerTipi ?: (string) config('muhasebe.doviz.tcmb_deger_tipi', 'ForexSelling');
        $harita = ['TRY' => '1.00000000'];
        foreach ($xml->Currency as $satir) {
            $kod = strtoupper((string) $satir['CurrencyCode']);
            if ($kod === '') {
                continue;
            }

            $deger = '';
            foreach ($this->tcmbDegerOnceligi($secim) as $alan) {
                $deger = trim((string) ($satir->{$alan} ?? ''));
                if ($deger !== '') {
                    break;
                }
            }
            if ($deger === '') {
                continue;
            }

            $deger = str_replace(',', '.', $deger);
            if (! is_numeric($deger)) {
                continue;
            }

            $unit = (int) ($satir->Unit ?? 1);
            $unit = $unit > 0 ? $unit : 1;
            $try = ((float) $deger) / $unit;
            $harita[$kod] = number_format($try, 8, '.', '');
        }

        return self::$tcmbGunlukKurCache[$cacheKey] = $harita;
    }

    private function tcmbDegerTipiBelirle(?string $kurTipi): string
    {
        return match (strtolower(trim((string) $kurTipi))) {
            'alis', 'forexbuying' => 'ForexBuying',
            'satis', 'forexselling' => 'ForexSelling',
            'efektif_alis', 'banknotebuying' => 'BanknoteBuying',
            'efektif_satis', 'banknoteselling' => 'BanknoteSelling',
            default => (string) config('muhasebe.doviz.tcmb_deger_tipi', 'ForexSelling'),
        };
    }

    /**
     * @return array<int, string>
     */
    private function tcmbDegerOnceligi(string $secim): array
    {
        return match ($secim) {
            'ForexBuying' => ['ForexBuying', 'ForexSelling', 'BanknoteBuying', 'BanknoteSelling'],
            'ForexSelling' => ['ForexSelling', 'ForexBuying', 'BanknoteSelling', 'BanknoteBuying'],
            'BanknoteBuying' => ['BanknoteBuying', 'ForexBuying', 'ForexSelling', 'BanknoteSelling'],
            'BanknoteSelling' => ['BanknoteSelling', 'ForexSelling', 'ForexBuying', 'BanknoteBuying'],
            default => ['ForexSelling', 'ForexBuying', 'BanknoteSelling', 'BanknoteBuying'],
        };
    }

    private function tcmbUrl(Carbon $tarih): string
    {
        $base = rtrim((string) config('muhasebe.doviz.tcmb_base_url', 'https://www.tcmb.gov.tr/kurlar'), '/');
        $yilAy = $tarih->format('Ym');
        $gun = $tarih->format('dmY');

        return $base.'/'.$yilAy.'/'.$gun.'.xml';
    }

    private function tryDegeri(string $kod, array $kurlarTry): ?string
    {
        if ($kod === 'TRY') {
            return '1.00000000';
        }

        return $kurlarTry[$kod] ?? null;
    }

    private function bazParaBirimi(): string
    {
        return strtoupper((string) config('muhasebe.coklu_para_birimi.baz_para_birimi', 'TRY'));
    }

    /**
     * @return array<int,string>
     */
    private function firmaAktifParaBirimleri(int $firmaId): array
    {
        $kodlar = ParaBirimi::tenantScopeOlmadan(function () use ($firmaId): array {
            return ParaBirimi::query()
                ->where('aktif_mi', true)
                ->gorunurFirmaIle($firmaId)
                ->orderBy('kod')
                ->pluck('kod')
                ->map(fn ($k): string => strtoupper((string) $k))
                ->unique()
                ->values()
                ->all();
        });

        $baz = $this->bazParaBirimi();
        if (! in_array($baz, $kodlar, true)) {
            $kodlar[] = $baz;
        }

        return $kodlar;
    }
}
