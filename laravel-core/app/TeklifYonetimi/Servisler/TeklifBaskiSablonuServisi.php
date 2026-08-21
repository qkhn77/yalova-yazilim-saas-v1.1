<?php

namespace App\TeklifYonetimi\Servisler;

use App\Models\Firma;
use App\Models\Muhasebe\Teklif;
use App\Models\TeklifYonetimi\TeklifBaskiSablonu;
use App\Services\FirmaAyarDeposu;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TeklifBaskiSablonuServisi
{
    private static ?bool $sablonTablosuVarMi = null;

    /** @var array<int, TeklifBaskiSablonu|null> */
    private static array $varsayilanSablonCache = [];

    /** @var array<int, int|null> */
    private static array $varsayilanSablonIdCache = [];

    public function sablonTablosuVarMi(): bool
    {
        return self::$sablonTablosuVarMi ??= Schema::hasTable('teklif_baski_sablonlari');
    }

    public function firmaSablonlariniHazirla(int $firmaId): void
    {
        if ($firmaId < 1 || ! $this->sablonTablosuVarMi()) {
            return;
        }

        $hazirCacheKey = $this->firmaHazirSablonCacheKey($firmaId);
        if (Cache::get($hazirCacheKey) === true) {
            return;
        }

        $mevcutKodlar = TeklifBaskiSablonu::query()
            ->where('firma_id', $firmaId)
            ->whereIn('kod', $this->hazirSablonKodlari())
            ->pluck('kod')
            ->all();

        if (count(array_unique($mevcutKodlar)) === count($this->hazirSablonKodlari())) {
            Cache::put($hazirCacheKey, true, now()->addMinutes(30));

            return;
        }

        $varsayilanVar = TeklifBaskiSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('varsayilan_mi', true)
            ->exists();

        foreach ($this->hazirSablonlar() as $index => $sablon) {
            $mevcut = TeklifBaskiSablonu::query()
                ->where('firma_id', $firmaId)
                ->where('kod', $sablon['kod'])
                ->exists();

            if ($mevcut) {
                continue;
            }

            TeklifBaskiSablonu::query()->create([
                'firma_id' => $firmaId,
                'ad' => $sablon['ad'],
                'kod' => $sablon['kod'],
                'sayfa_tipi' => $sablon['sayfa_tipi'],
                'sablon_logo' => null,
                'sablon_html' => $sablon['sablon_html'],
                'sablon_css' => $sablon['sablon_css'],
                'varsayilan_mi' => ! $varsayilanVar && $index === 0,
                'aktif' => true,
            ]);

            unset(self::$varsayilanSablonCache[$firmaId], self::$varsayilanSablonIdCache[$firmaId]);

            if (! $varsayilanVar && $index === 0) {
                $varsayilanVar = true;
            }
        }

        Cache::put($hazirCacheKey, true, now()->addMinutes(30));
    }

    public function varsayilanYap(TeklifBaskiSablonu $sablon): void
    {
        if (! $this->sablonTablosuVarMi()) {
            return;
        }

        $firmaId = (int) $sablon->firma_id;

        unset(self::$varsayilanSablonCache[$firmaId], self::$varsayilanSablonIdCache[$firmaId]);
        Cache::forget($this->firmaHazirSablonCacheKey($firmaId));

        DB::transaction(function () use ($sablon): void {
            TeklifBaskiSablonu::query()
                ->where('firma_id', (int) $sablon->firma_id)
                ->update(['varsayilan_mi' => false]);

            $sablon->forceFill(['varsayilan_mi' => true])->save();
        });
    }

    public function kopyala(TeklifBaskiSablonu $sablon): TeklifBaskiSablonu
    {
        $sablon = TeklifBaskiSablonu::query()->findOrFail($sablon->getKey());
        $firmaId = (int) $sablon->firma_id;
        $temelAd = preg_replace('/^Kopya(?: \d+)? /u', '', trim((string) $sablon->ad)) ?: (string) $sablon->ad;
        $sayac = 1;

        do {
            $etiket = $sayac === 1 ? 'Kopya' : 'Kopya '.$sayac;
            $ad = $etiket.' '.$temelAd;
            $kod = $this->benzersizKodUret($firmaId, $ad);
            $varMi = TeklifBaskiSablonu::query()
                ->where('firma_id', $firmaId)
                ->where('ad', $ad)
                ->exists();
            $sayac++;
        } while ($varMi);

        return TeklifBaskiSablonu::query()->create([
            'firma_id' => $firmaId,
            'ad' => $ad,
            'kod' => $kod,
            'sayfa_tipi' => (string) $sablon->sayfa_tipi,
            'sablon_logo' => $sablon->sablon_logo,
            'sablon_html' => $sablon->sablon_html,
            'sablon_css' => $sablon->sablon_css,
            'aktif' => (bool) $sablon->aktif,
            'varsayilan_mi' => false,
        ]);
    }

    public function benzersizKodUret(int $firmaId, string $ad): string
    {
        $temel = Str::of($ad)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->value();

        $temel = $temel !== '' ? $temel : 'teklif-sablonu';
        $kod = $temel;
        $sayac = 2;

        while ($this->sablonTablosuVarMi() && TeklifBaskiSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('kod', $kod)
            ->exists()) {
            $kod = $temel.'-'.$sayac;
            $sayac++;
        }

        return $kod;
    }

    /**
     * @return array<int, array{ad:string,kod:string,sayfa_tipi:string,sablon_html:string,sablon_css:string}>
     */
    public function hazirSablonlar(): array
    {
        return [
            [
                'ad' => 'Yalova Bilgisayar Teklif Formu A4',
                'kod' => 'yalova-bilgisayar-teklif-formu-a4',
                'sayfa_tipi' => 'a4',
                'sablon_html' => $this->yalovaBilgisayarSablonHtml(),
                'sablon_css' => $this->cssYalovaBilgisayarA4(),
            ],
            [
                'ad' => 'PC Teklif Şablonu A4',
                'kod' => 'pc-teklif-a4',
                'sayfa_tipi' => 'a4',
                'sablon_html' => $this->pcSablonHtml(),
                'sablon_css' => $this->cssPcA4(),
            ],
            [
                'ad' => 'EAS Teklif Şablonu A4',
                'kod' => 'eas-teklif-a4',
                'sayfa_tipi' => 'a4',
                'sablon_html' => $this->easSablonHtml(),
                'sablon_css' => $this->cssEasA4(),
            ],
            [
                'ad' => 'Privacy Teklif Şablonu A4',
                'kod' => 'privacy-teklif-a4',
                'sayfa_tipi' => 'a4',
                'sablon_html' => $this->privacySablonHtml(),
                'sablon_css' => $this->cssPrivacyA4(),
            ],
            [
                'ad' => 'Technology Teklif Şablonu A4',
                'kod' => 'technology-teklif-a4',
                'sayfa_tipi' => 'a4',
                'sablon_html' => $this->technologySablonHtml(),
                'sablon_css' => $this->cssTechnologyA4(),
            ],
            [
                'ad' => 'Consultancy Teklif Şablonu A4',
                'kod' => 'consultancy-teklif-a4',
                'sayfa_tipi' => 'a4',
                'sablon_html' => $this->consultancySablonHtml(),
                'sablon_css' => $this->cssConsultancyA4(),
            ],
            [
                'ad' => 'Teklif Şablonu A4',
                'kod' => 'teklif-a4',
                'sayfa_tipi' => 'a4',
                'sablon_html' => $this->temelSablonHtml(),
                'sablon_css' => $this->cssA4(),
            ],
            [
                'ad' => 'Teklif Şablonu A5',
                'kod' => 'teklif-a5',
                'sayfa_tipi' => 'a5',
                'sablon_html' => $this->temelSablonHtml(),
                'sablon_css' => $this->cssA5(),
            ],
            [
                'ad' => 'Teklif Şablonu 80mm',
                'kod' => 'teklif-80mm',
                'sayfa_tipi' => '80mm',
                'sablon_html' => $this->miniSablonHtml(),
                'sablon_css' => $this->css80mm(),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function hazirSablonKodlari(): array
    {
        return [
            'yalova-bilgisayar-teklif-formu-a4',
            'pc-teklif-a4',
            'eas-teklif-a4',
            'privacy-teklif-a4',
            'technology-teklif-a4',
            'consultancy-teklif-a4',
            'teklif-a4',
            'teklif-a5',
            'teklif-80mm',
        ];
    }

    /**
     * @param  array<string, mixed>  $kaynak
     */
    public function onizlemeHtmlOlustur(array $kaynak, int $firmaId): string
    {
        $html = trim((string) ($kaynak['sablon_html'] ?? ''));
        if ($html === '') {
            return '<div style="font-size:14px;color:#64748b;">Ön izleme için şablon içeriği girin.</div>';
        }

        $firma = Firma::query()->find($firmaId);
        $logoUrl = $this->logoUrlHazirla((string) ($kaynak['sablon_logo'] ?? '')) ?: $this->firmaLogoUrl($firmaId);

        $satirlar = [
            ['ad' => '16 Port PoE Switch', 'miktar' => 1, 'birim_fiyat' => 139.23, 'toplam' => 139.23],
            ['ad' => '1kVA Online UPS', 'miktar' => 1, 'birim_fiyat' => 250.63, 'toplam' => 250.63],
            ['ad' => '4MP Smart Hybrid Bullet Kamera', 'miktar' => 6, 'birim_fiyat' => 73.34, 'toplam' => 440.04],
        ];

        $tokens = $this->teklifTokenlariniHazirla(
            firma: $firma,
            cari: null,
            logoUrl: $logoUrl,
            teklifNo: 'TKL-2026-0001',
            teklifTarihi: now()->format('d.m.Y'),
            gecerlilikTarihi: now()->addDays(15)->format('d.m.Y'),
            musteriAd: 'Örnek Cari / Müşteri',
            musteriTelefon: '+90 (555) 000 00 00',
            musteriEposta: 'musteri@example.com',
            musteriAdres: 'Yalova / Merkez',
            teklifBasligi: 'Kamera Sistemi Kurulum Teklifi',
            teklifAciklama: 'Keşif, kurulum ve devreye alma hizmetleri teklif kapsamındadır.',
            teslimSuresi: '7 iş günü',
            paraBirimi: 'USD',
            kalemler: $satirlar,
            araToplam: '829,90 USD',
            toplamIndirim: '0,00 USD',
            kdvToplam: '165,98 USD',
            genelToplam: '995,88 USD',
            notlar: 'Kurulum ve devreye alma hizmeti dahildir.',
            kosullar: 'Teklif 15 gün geçerlidir. Stok durumuna göre teslim süresi değişebilir.',
            odemePlani: '%50 peşin, %50 teslim sonrası',
        );

        return strtr($html, $tokens);
    }

    public function teklifHtmlOlustur(Teklif $teklif): string
    {
        $sablon = $teklif->baskiSablonu ?: $this->varsayilanSablon((int) $teklif->firma_id);
        if (! $sablon) {
            return '<div style="font-size:14px;color:#64748b;">Ön izleme şablonu bulunamadı.</div>';
        }

        $html = trim((string) ($sablon->sablon_html ?? ''));
        if ($html === '') {
            return '<div style="font-size:14px;color:#64748b;">Seçili ön izleme şablonu boş.</div>';
        }

        $firma = $teklif->firma;
        $cari = $teklif->cari;
        $logoUrl = $this->logoUrlHazirla((string) ($sablon->sablon_logo ?? '')) ?: $this->firmaLogoUrl((int) $teklif->firma_id);
        $paraBirimi = (string) ($teklif->para_birimi ?: 'TRY');
        $toplamlar = $teklif->kalemToplamlariniHesapla();

        $tokens = $this->teklifTokenlariniHazirla(
            firma: $firma,
            cari: $cari,
            logoUrl: $logoUrl,
            teklifNo: (string) ($teklif->teklif_no ?: ''),
            teklifTarihi: optional($teklif->tarih)->format('d.m.Y') ?: '',
            gecerlilikTarihi: optional($teklif->gecerlilik_tarihi)->format('d.m.Y') ?: '',
            musteriAd: (string) ($cari?->ad ?: ''),
            musteriTelefon: (string) ($cari?->telefon ?: $cari?->gsm ?: ''),
            musteriEposta: (string) ($cari?->email ?: ''),
            musteriAdres: $this->musteriAdresiniOlustur($cari),
            teklifBasligi: (string) ($teklif->baslik ?: ''),
            teklifAciklama: (string) ($teklif->aciklama ?: ''),
            teslimSuresi: (string) ($teklif->teslim_suresi ?: ''),
            paraBirimi: $paraBirimi,
            kalemler: $teklif->kalemler,
            araToplam: number_format((float) $toplamlar['ara_toplam'], 2, ',', '.').' '.$paraBirimi,
            toplamIndirim: number_format((float) $toplamlar['toplam_indirim'], 2, ',', '.').' '.$paraBirimi,
            kdvToplam: number_format((float) $toplamlar['kdv_toplam'], 2, ',', '.').' '.$paraBirimi,
            genelToplam: number_format((float) $toplamlar['genel_toplam'], 2, ',', '.').' '.$paraBirimi,
            notlar: (string) ($teklif->notlar ?: ''),
            kosullar: (string) ($teklif->kosullar ?: ''),
            odemePlani: (string) ($teklif->odeme_plani ?: ''),
        );

        return strtr($html, $tokens);
    }

    /**
     * @param  iterable<int, mixed>  $kalemler
     * @return array<string, string>
     */
    private function teklifTokenlariniHazirla(
        ?Firma $firma,
        mixed $cari,
        ?string $logoUrl,
        string $teklifNo,
        string $teklifTarihi,
        string $gecerlilikTarihi,
        string $musteriAd,
        string $musteriTelefon,
        string $musteriEposta,
        string $musteriAdres,
        string $teklifBasligi,
        string $teklifAciklama,
        string $teslimSuresi,
        string $paraBirimi,
        iterable $kalemler,
        string $araToplam,
        string $toplamIndirim,
        string $kdvToplam,
        string $genelToplam,
        string $notlar,
        string $kosullar,
        string $odemePlani,
    ): array {
        $kalemSatirlari = $this->kalemSatirlariniHazirla($kalemler, $paraBirimi);
        $kalemlerTablosu = $this->kalemTablosuHazirla($kalemSatirlari);
        $kalemlerTablosuNumarali = $this->kalemTablosuHazirla($kalemSatirlari, true);
        $musteriVergiTc = trim((string) ($cari?->vergi_no ?: $cari?->tc_no ?: ''));
        $musteriYetkili = trim((string) ($cari?->yetkili_kisi ?: ''));
        $iskontoOrani = $this->iskontoOraniHazirla($kalemler);

        return [
            '{{FIRMA_UNVAN}}' => e((string) ($firma?->ad ?: 'Yalova Kamera')),
            '{{FIRMA_TELEFON}}' => e((string) ($firma?->telefon ?: '')),
            '{{FIRMA_EPOSTA}}' => e((string) ($firma?->eposta ?: '')),
            '{{FIRMA_ADRES}}' => e((string) ($firma?->adres ?: '')),
            '{{FIRMA_LOGO}}' => '<img src="'.e($logoUrl ?: $this->varsayilanLogoUrl()).'" alt="Firma logosu">',
            '{{TEKLIF_NO}}' => e($teklifNo),
            '{{TEKLIF_TARIHI}}' => e($teklifTarihi),
            '{{GECERLILIK_TARIHI}}' => e($gecerlilikTarihi),
            '{{MUSTERI_AD}}' => e($musteriAd),
            '{{MUSTERI_TELEFON}}' => e($musteriTelefon),
            '{{MUSTERI_EPOSTA}}' => e($musteriEposta),
            '{{MUSTERI_ADRES}}' => e($musteriAdres),
            '{{MUSTERI_VERGI_TC}}' => e($musteriVergiTc),
            '{{MUSTERI_YETKILI}}' => e($musteriYetkili),
            '{{TEKLIF_BASLIGI}}' => e($teklifBasligi),
            '{{TEKLIF_ACIKLAMA}}' => nl2br(e($teklifAciklama)),
            '{{TESLIM_SURESI}}' => e($teslimSuresi),
            '{{PARA_BIRIMI}}' => e($paraBirimi),
            '{{YB_MARK_LOGO}}' => '<img src="'.e($logoUrl ?: $this->varsayilanLogoUrl()).'" alt="YB logo">',
            '{{KALEMLER_TABLOSU}}' => $kalemlerTablosu,
            '{{KALEMLER_TABLOSU_NUMARALI}}' => $kalemlerTablosuNumarali,
            '{{YB_KALEM_TABLOSU}}' => $this->yalovaBilgisayarKalemTablosuHazirla($kalemSatirlari),
            '{{KALEM_OVERLAY_PRIVACY}}' => $this->kalemOverlayTablosuHazirla($kalemSatirlari, 'privacy'),
            '{{KALEM_OVERLAY_TECHNOLOGY}}' => $this->kalemOverlayTablosuHazirla($kalemSatirlari, 'technology'),
            '{{KALEM_OVERLAY_CONSULTANCY}}' => $this->kalemOverlayTablosuHazirla($kalemSatirlari, 'consultancy'),
            '{{TOPLAM_OVERLAY_PRIVACY}}' => $this->toplamOverlayHazirla('privacy', $araToplam, $kdvToplam, $genelToplam),
            '{{TOPLAM_OVERLAY_TECHNOLOGY}}' => $this->toplamOverlayHazirla('technology', $araToplam, $kdvToplam, $genelToplam),
            '{{TOPLAM_OVERLAY_CONSULTANCY}}' => $this->toplamOverlayHazirla('consultancy', $araToplam, $kdvToplam, $genelToplam),
            '{{ARA_TOPLAM}}' => e($araToplam),
            '{{ISKONTO_ORANI}}' => e($iskontoOrani),
            '{{TOPLAM_INDIRIM}}' => e($toplamIndirim),
            '{{KDV_TOPLAM}}' => e($kdvToplam),
            '{{GENEL_TOPLAM}}' => e($genelToplam),
            '{{NOTLAR}}' => nl2br(e($notlar)),
            '{{KOSULLAR}}' => nl2br(e($kosullar)),
            '{{ODEME_PLANI}}' => nl2br(e($odemePlani)),
        ] + $this->kalemTokenlariniHazirla($kalemSatirlari);
    }

    private function musteriAdresiniOlustur(mixed $cari): string
    {
        if (! $cari) {
            return '';
        }

        $parcalar = array_filter([
            (string) ($cari->adres ?? ''),
            trim(implode(' / ', array_filter([
                (string) ($cari->ilce ?? ''),
                (string) ($cari->il ?? ''),
            ]))),
            (string) ($cari->posta_kodu ?? ''),
        ], static fn (?string $deger): bool => filled($deger));

        return implode(' ', $parcalar);
    }

    private function iskontoOraniHazirla(iterable $kalemler): string
    {
        $oranlar = [];

        foreach ($kalemler as $kalem) {
            $oran = is_array($kalem)
                ? (float) ($kalem['indirim_orani'] ?? 0)
                : (float) ($kalem->indirim_orani ?? 0);

            if ($oran > 0) {
                $oranlar[] = round($oran, 2);
            }
        }

        if ($oranlar === []) {
            return '0%';
        }

        $benzersizOranlar = array_values(array_unique($oranlar));

        if (count($benzersizOranlar) > 1) {
            return 'Çeşitli';
        }

        return rtrim(rtrim(number_format((float) $benzersizOranlar[0], 2, ',', '.'), '0'), ',').'%';
    }

    /**
     * @param  array<int, array{ad:string,miktar:string,birim_fiyat:string,toplam:string}>  $satirlar
     */
    private function kalemTablosuHazirla(array $satirlar, bool $numarali = false): string
    {
        if ($satirlar === []) {
            $satirlar[] = [
                'ad' => '',
                'miktar' => '',
                'birim_fiyat' => '',
                'toplam' => '',
            ];
        }

        $html = '';

        foreach ($satirlar as $index => $satir) {
            $html .= '<tr>';

            if ($numarali) {
                $html .= '<td>'.e((string) ($index + 1)).'</td>';
            }

            $html .= '<td>'.e($satir['ad']).'</td>'
                .'<td>'.e($satir['miktar']).'</td>'
                .'<td>'.e($satir['birim_fiyat']).'</td>'
                .'<td>'.e($satir['toplam']).'</td>'
                .'</tr>';
        }

        return $html;
    }

    /**
     * @param  array<int, array{ad:string,miktar:string,birim_fiyat:string,toplam:string}>  $satirlar
     */
    private function yalovaBilgisayarKalemTablosuHazirla(array $satirlar): string
    {
        $html = '';

        for ($i = 0; $i < 15; $i++) {
            $satir = $satirlar[$i] ?? [
                'ad' => '',
                'miktar' => '',
                'birim_fiyat' => '',
                'toplam' => '',
            ];

            $html .= '<tr>'
                .'<td>'.e((string) ($i + 1)).'</td>'
                .'<td>'.e($satir['ad']).'</td>'
                .'<td>'.e($satir['miktar']).'</td>'
                .'<td>'.e($satir['birim_fiyat']).'</td>'
                .'<td>'.e($satir['toplam']).'</td>'
                .'</tr>';
        }

        return $html;
    }

    /**
     * @param  iterable<int, mixed>  $kalemler
     * @return array<int, array{ad:string,miktar:string,birim_fiyat:string,toplam:string}>
     */
    private function kalemSatirlariniHazirla(iterable $kalemler, string $paraBirimi): array
    {
        $satirlar = [];

        foreach ($kalemler as $kalem) {
            if (is_array($kalem)) {
                $ad = (string) ($kalem['ad'] ?? 'Serbest kalem');
                $miktar = (string) ((int) round((float) ($kalem['miktar'] ?? 0)));
                $birimFiyat = number_format((float) ($kalem['birim_fiyat'] ?? 0), 2, ',', '.').' '.$paraBirimi;
                $toplam = number_format((float) ($kalem['toplam'] ?? 0), 2, ',', '.').' '.$paraBirimi;
            } else {
                $ad = (string) ($kalem->stokKarti?->ad ?: $kalem->aciklama ?: 'Serbest kalem');
                $miktar = (string) ((int) round((float) $kalem->miktar));
                $birimFiyat = number_format((float) $kalem->birim_fiyat, 2, ',', '.').' '.$paraBirimi;
                $toplam = number_format((float) $kalem->toplam, 2, ',', '.').' '.$paraBirimi;
            }

            $satirlar[] = [
                'ad' => $ad,
                'miktar' => $miktar,
                'birim_fiyat' => $birimFiyat,
                'toplam' => $toplam,
            ];
        }

        return $satirlar;
    }

    /**
     * @param  array<int, array{ad:string,miktar:string,birim_fiyat:string,toplam:string}>  $satirlar
     * @return array<string, string>
     */
    private function kalemTokenlariniHazirla(array $satirlar): array
    {
        $tokenlar = [];

        for ($i = 1; $i <= 8; $i++) {
            $satir = $satirlar[$i - 1] ?? ['ad' => '', 'miktar' => '', 'birim_fiyat' => '', 'toplam' => ''];
            $tokenlar["{{KALEM_{$i}_AD}}"] = e($satir['ad']);
            $tokenlar["{{KALEM_{$i}_MIKTAR}}"] = e($satir['miktar']);
            $tokenlar["{{KALEM_{$i}_FIYAT}}"] = e($satir['birim_fiyat']);
            $tokenlar["{{KALEM_{$i}_TOPLAM}}"] = e($satir['toplam']);
        }

        return $tokenlar;
    }

    /**
     * @param  array<int, array{ad:string,miktar:string,birim_fiyat:string,toplam:string}>  $satirlar
     */
    private function kalemOverlayTablosuHazirla(array $satirlar, string $sablon): string
    {
        $gorunecekSatirlar = array_slice($satirlar, 0, 6);

        if ($gorunecekSatirlar === []) {
            $gorunecekSatirlar[] = [
                'ad' => '',
                'miktar' => '',
                'birim_fiyat' => '',
                'toplam' => '',
            ];
        }

        $basliklar = match ($sablon) {
            'technology' => ['Description', 'Price', 'Qty', 'Total'],
            default => ['Qty', 'Description', 'Price', 'Total'],
        };

        $satirlarHtml = implode('', array_map(function (array $satir) use ($sablon): string {
            $hucreler = match ($sablon) {
                'technology' => [
                    '<span class="teklif-overlay__cell teklif-overlay__cell--desc">'.e($satir['ad']).'</span>',
                    '<span class="teklif-overlay__cell teklif-overlay__cell--money">'.e($satir['birim_fiyat']).'</span>',
                    '<span class="teklif-overlay__cell teklif-overlay__cell--qty">'.e($satir['miktar']).'</span>',
                    '<span class="teklif-overlay__cell teklif-overlay__cell--money">'.e($satir['toplam']).'</span>',
                ],
                default => [
                    '<span class="teklif-overlay__cell teklif-overlay__cell--qty">'.e($satir['miktar']).'</span>',
                    '<span class="teklif-overlay__cell teklif-overlay__cell--desc">'.e($satir['ad']).'</span>',
                    '<span class="teklif-overlay__cell teklif-overlay__cell--money">'.e($satir['birim_fiyat']).'</span>',
                    '<span class="teklif-overlay__cell teklif-overlay__cell--money">'.e($satir['toplam']).'</span>',
                ],
            };

            return '<div class="teklif-overlay__row">'.implode('', $hucreler).'</div>';
        }, $gorunecekSatirlar));

        $sinif = 'teklif-overlay teklif-overlay--'.$sablon;

        return '<div class="'.e($sinif).'">'
            .'<div class="teklif-overlay__head">'
            .'<span class="teklif-overlay__cell teklif-overlay__cell--head">'.e($basliklar[0]).'</span>'
            .'<span class="teklif-overlay__cell teklif-overlay__cell--head">'.e($basliklar[1]).'</span>'
            .'<span class="teklif-overlay__cell teklif-overlay__cell--head">'.e($basliklar[2]).'</span>'
            .'<span class="teklif-overlay__cell teklif-overlay__cell--head">'.e($basliklar[3]).'</span>'
            .'</div>'
            .'<div class="teklif-overlay__body">'.$satirlarHtml.'</div>'
            .'</div>';
    }

    private function toplamOverlayHazirla(string $sablon, string $araToplam, string $kdvToplam, string $genelToplam): string
    {
        $sinif = 'teklif-total-overlay teklif-total-overlay--'.$sablon;

        return '<div class="'.e($sinif).'">'
            .'<div class="teklif-total-overlay__row"><span>Ara Toplam</span><strong>'.e($araToplam).'</strong></div>'
            .'<div class="teklif-total-overlay__row"><span>KDV</span><strong>'.e($kdvToplam).'</strong></div>'
            .'<div class="teklif-total-overlay__row teklif-total-overlay__row--grand"><span>Genel Toplam</span><strong>'.e($genelToplam).'</strong></div>'
            .'</div>';
    }

    public function kapsayiciStili(string $sayfaTipi): string
    {
        return match ($sayfaTipi) {
            'a5' => 'max-width: 148mm; min-height: 210mm;',
            '80mm' => 'max-width: 80mm; min-height: 100mm;',
            default => 'max-width: 210mm; min-height: 297mm;',
        };
    }

    public function sayfaSagBosluk(string $sayfaTipi): string
    {
        return match ($sayfaTipi) {
            '80mm' => '3mm',
            default => '0mm',
        };
    }

    public function varsayilanSablon(int $firmaId): ?TeklifBaskiSablonu
    {
        if ($firmaId < 1 || ! $this->sablonTablosuVarMi()) {
            return null;
        }

        if (array_key_exists($firmaId, self::$varsayilanSablonCache)) {
            return self::$varsayilanSablonCache[$firmaId];
        }

        return self::$varsayilanSablonCache[$firmaId] = TeklifBaskiSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('aktif', true)
            ->orderByDesc('varsayilan_mi')
            ->orderBy('id')
            ->first();
    }

    public function varsayilanSablonId(int $firmaId): ?int
    {
        if ($firmaId < 1 || ! $this->sablonTablosuVarMi()) {
            return null;
        }

        if (array_key_exists($firmaId, self::$varsayilanSablonIdCache)) {
            return self::$varsayilanSablonIdCache[$firmaId];
        }

        $id = TeklifBaskiSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('aktif', true)
            ->orderByDesc('varsayilan_mi')
            ->orderBy('id')
            ->value('id');

        return self::$varsayilanSablonIdCache[$firmaId] = $id !== null ? (int) $id : null;
    }

    private function firmaHazirSablonCacheKey(int $firmaId): string
    {
        return 'teklif_hazir_sablonlar|'.$firmaId;
    }

    public function hazirVarsayilanSablonId(int $firmaId): ?int
    {
        $id = $this->varsayilanSablonId($firmaId);

        if ($id !== null) {
            return $id;
        }

        $this->firmaSablonlariniHazirla($firmaId);
        unset(self::$varsayilanSablonCache[$firmaId], self::$varsayilanSablonIdCache[$firmaId]);

        return $this->varsayilanSablonId($firmaId);
    }

    private function temelSablonHtml(): string
    {
        return <<<'HTML'
<div class="teklif-wrap">
    <header class="teklif-header">
        <div class="teklif-brand">
            <div class="teklif-logo">{{FIRMA_LOGO}}</div>
            <div class="teklif-firma">
                <div class="teklif-eyebrow">TEKLİF BELGESİ</div>
                <h1>{{FIRMA_UNVAN}}</h1>
                <div>{{FIRMA_TELEFON}} | {{FIRMA_EPOSTA}}</div>
                <div>{{FIRMA_ADRES}}</div>
            </div>
        </div>
        <div class="teklif-meta">
            <div><strong>Teklif No:</strong> {{TEKLIF_NO}}</div>
            <div><strong>Tarih:</strong> {{TEKLIF_TARIHI}}</div>
            <div><strong>Geçerlilik:</strong> {{GECERLILIK_TARIHI}}</div>
        </div>
    </header>

    <section class="teklif-section">
        <div class="teklif-section-title">Müşteri Bilgileri</div>
        <div class="teklif-grid teklif-grid-3">
            <div><strong>Müşteri:</strong> {{MUSTERI_AD}}</div>
            <div><strong>Telefon:</strong> {{MUSTERI_TELEFON}}</div>
            <div><strong>E-posta:</strong> {{MUSTERI_EPOSTA}}</div>
        </div>
    </section>

    <section class="teklif-section">
        <div class="teklif-section-title">Teklif Konusu</div>
        <div class="teklif-subject">{{TEKLIF_BASLIGI}}</div>
    </section>

    <section class="teklif-section">
        <div class="teklif-section-title">Kalemler</div>
        <table class="teklif-table">
            <thead>
                <tr>
                    <th>Ürün / Hizmet</th>
                    <th>Miktar</th>
                    <th>Birim Fiyat</th>
                    <th>Tutar</th>
                </tr>
            </thead>
            <tbody>
                {{KALEMLER_TABLOSU}}
            </tbody>
        </table>
    </section>

    <section class="teklif-total-box">
        <div><span>Ara Toplam</span><strong>{{ARA_TOPLAM}}</strong></div>
        <div><span>İndirim</span><strong>{{TOPLAM_INDIRIM}}</strong></div>
        <div><span>KDV</span><strong>{{KDV_TOPLAM}}</strong></div>
        <div class="grand"><span>Genel Toplam</span><strong>{{GENEL_TOPLAM}}</strong></div>
    </section>

    <section class="teklif-notes">
        <div class="note-block">
            <div class="teklif-section-title">Notlar</div>
            <div>{{NOTLAR}}</div>
        </div>
        <div class="note-block">
            <div class="teklif-section-title">Şartlar</div>
            <div>{{KOSULLAR}}</div>
        </div>
        <div class="note-block">
            <div class="teklif-section-title">Ödeme Planı</div>
            <div>{{ODEME_PLANI}}</div>
        </div>
    </section>
</div>
HTML;
    }

    private function yalovaBilgisayarSablonHtml(): string
    {
        return <<<'HTML'
<div class="yb-offer-page">
    <div class="yb-offer-header">
        <div class="yb-offer-brand">
            <div class="yb-offer-brand__inner">
                <div class="yb-offer-mark">{{YB_MARK_LOGO}}</div>
                <div class="yb-offer-brand__copy">
                    <strong>Yalova</strong>
                    <span>Bilgisayar</span>
                    <em>Teknik Servis</em>
                </div>
            </div>
        </div>

        <h1 class="yb-offer-title"><span>TEKLİF</span> FORMU</h1>

        <div class="yb-offer-contact">
            <div class="yb-offer-contact__item">
                <span class="yb-offer-contact__icon">📍</span>
                <div class="yb-offer-contact__text">Sahil Mah. Yalı Cad. No:3/A Çiftlikköy / Yalova</div>
            </div>
            <div class="yb-offer-contact__item">
                <span class="yb-offer-contact__icon">🧾</span>
                <div class="yb-offer-contact__text">Vergi Dairesi: Yalova<br>Vergi No: 451999618384</div>
            </div>
            <div class="yb-offer-contact__item">
                <span class="yb-offer-contact__icon">☎</span>
                <div class="yb-offer-contact__text">Tel: 0 (226) 352 07 24<br>Cep: 0 (553) 979 32 55</div>
            </div>
            <div class="yb-offer-contact__item">
                <span class="yb-offer-contact__icon">🌐</span>
                <div class="yb-offer-contact__text">www.yalovabilgisayar.com</div>
            </div>
            <div class="yb-offer-contact__item">
                <span class="yb-offer-contact__icon">✉</span>
                <div class="yb-offer-contact__text">info@yalovabilgisayar.com</div>
            </div>
        </div>
    </div>

    <div class="yb-offer-body">
        <div class="yb-offer-info-grid">
            <section class="yb-offer-panel">
                <div class="yb-offer-panel__label">MÜŞTERİ BİLGİLERİ</div>
                <div class="yb-offer-fields">
                    <div class="yb-offer-field"><strong>Kişi / Kurum Adı</strong><span>:</span><div class="yb-offer-line">{{MUSTERI_AD}}</div></div>
                    <div class="yb-offer-field yb-offer-field--multiline"><strong>Adres</strong><span>:</span><div class="yb-offer-line">{{MUSTERI_ADRES}}</div></div>
                    <div class="yb-offer-field"><strong>Telefon</strong><span>:</span><div class="yb-offer-line">{{MUSTERI_TELEFON}}</div></div>
                </div>
            </section>

            <section class="yb-offer-panel">
                <div class="yb-offer-panel__label">TEKLİF BİLGİLERİ</div>
                <div class="yb-offer-fields">
                    <div class="yb-offer-field"><strong>Teklif No</strong><span>:</span><div class="yb-offer-line">{{TEKLIF_NO}}</div></div>
                    <div class="yb-offer-field"><strong>Teklif Tarihi</strong><span>:</span><div class="yb-offer-line">{{TEKLIF_TARIHI}}</div></div>
                    <div class="yb-offer-field"><strong>Geçerlilik Tarihi</strong><span>:</span><div class="yb-offer-line">{{GECERLILIK_TARIHI}}</div></div>
                </div>
            </section>
        </div>

        <div class="yb-offer-table-wrap">
            <table class="yb-offer-table">
                <thead>
                    <tr>
                        <th>SIRA<br>NO</th>
                        <th>ÜRÜN / HİZMET AÇIKLAMASI</th>
                        <th>ADET</th>
                        <th>B. FİYAT</th>
                        <th>T. FİYAT</th>
                    </tr>
                </thead>
                <tbody>
                    {{YB_KALEM_TABLOSU}}
                </tbody>
            </table>
        </div>

        <div class="yb-offer-bottom-grid">
            <section class="yb-offer-notes">
                <div class="yb-offer-section-title">NOTLAR / AÇIKLAMALAR</div>
                <ul>
                    <li>Teklifte belirtilen fiyatlar ve şartlar teklif geçerlilik süresi sonuna kadar geçerlidir.</li>
                    <li>Ödeme şartları ayrıca belirtilecektir. Fiyatlarımıza KDV dahil değildir.</li>
                    <li>Teslimat süresi, sipariş onayına müteakip belirlenecektir.</li>
                    <li>Teknik şartlar ve garanti koşulları, ürün/hizmete göre değişiklik gösterebilir.</li>
                </ul>

                <div class="yb-offer-warranty">
                    <div class="yb-offer-warranty__icon">🛡</div>
                    <p><strong>GARANTİ</strong> Ürünler fatura tarihinden itibaren <strong>2 YIL CİHAZ GARANTİSİ</strong> ve <strong>6 AY İŞÇİLİK GARANTİSİ</strong> kapsamındadır.</p>
                </div>

                <div class="yb-offer-notes__footer">
                    <section class="yb-offer-thanks">
                        <div class="yb-offer-thanks__icon">🤝</div>
                        <div>
                            <p>Bizi tercih ettiğiniz için teşekkür ederiz.</p>
                            <strong>Yalova Bilgisayar Teknik Servis</strong>
                        </div>
                    </section>
                </div>
            </section>

            <section class="yb-offer-summary">
                <table>
                    <tbody>
                        <tr><td>ARA TOPLAM</td><td>{{ARA_TOPLAM}}</td></tr>
                        <tr><td>İSKONTO ORANI</td><td>{{ISKONTO_ORANI}}</td></tr>
                        <tr><td>İSKONTO TUTARI</td><td>{{TOPLAM_INDIRIM}}</td></tr>
                        <tr class="yb-offer-summary__total"><td>SATIR TOPLAMI</td><td>{{GENEL_TOPLAM}}</td></tr>
                    </tbody>
                </table>

                <div class="yb-offer-summary__approval">
                    <h3>TEKLİFİ ONAYLAYAN</h3>
                    <p>Ad - Soyad / Kaşe - İmza</p>
                    <div class="yb-offer-signature"></div>
                </div>
            </section>
        </div>

        <div class="yb-offer-sign-row"></div>
    </div>

    <div class="yb-offer-disclaimer"></div>
</div>
HTML;
    }

    private function miniSablonHtml(): string
    {
        return <<<'HTML'
<div class="teklif-mini">
    <div class="mini-logo">{{FIRMA_LOGO}}</div>
    <div class="mini-title">{{FIRMA_UNVAN}}</div>
    <div class="mini-line"><strong>Teklif:</strong> {{TEKLIF_NO}}</div>
    <div class="mini-line"><strong>Müşteri:</strong> {{MUSTERI_AD}}</div>
    <div class="mini-line"><strong>Tarih:</strong> {{TEKLIF_TARIHI}}</div>
    <div class="mini-total">{{GENEL_TOPLAM}}</div>
</div>
HTML;
    }

    private function easSablonHtml(): string
    {
        return <<<'HTML'
<div class="eas-sheet">
    <div class="eas-topline"></div>

    <header class="eas-header">
        <div class="eas-brand">
            <div class="eas-logo">{{FIRMA_LOGO}}</div>
            <div class="eas-brand-copy">
                <div class="eas-kicker">PRICE QUOTATION</div>
                <h1>{{FIRMA_UNVAN}}</h1>
                <div class="eas-contact">{{FIRMA_ADRES}}</div>
                <div class="eas-contact">{{FIRMA_TELEFON}} | {{FIRMA_EPOSTA}}</div>
            </div>
        </div>
        <div class="eas-quote-box">
            <div class="eas-quote-row"><span>Quotation No</span><strong>{{TEKLIF_NO}}</strong></div>
            <div class="eas-quote-row"><span>Date</span><strong>{{TEKLIF_TARIHI}}</strong></div>
            <div class="eas-quote-row"><span>Valid Until</span><strong>{{GECERLILIK_TARIHI}}</strong></div>
        </div>
    </header>

    <section class="eas-block">
        <div class="eas-block-title">Customer</div>
        <div class="eas-customer-grid">
            <div class="eas-customer-item">
                <span>Company / Name</span>
                <strong>{{MUSTERI_AD}}</strong>
            </div>
            <div class="eas-customer-item">
                <span>Phone</span>
                <strong>{{MUSTERI_TELEFON}}</strong>
            </div>
            <div class="eas-customer-item">
                <span>E-mail</span>
                <strong>{{MUSTERI_EPOSTA}}</strong>
            </div>
        </div>
    </section>

    <section class="eas-block">
        <div class="eas-block-title">Subject</div>
        <div class="eas-subject">{{TEKLIF_BASLIGI}}</div>
    </section>

    <section class="eas-block">
        <div class="eas-block-title">Quotation Details</div>
        <table class="eas-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="qty">Qty</th>
                    <th class="money">Unit Price</th>
                    <th class="money">Amount</th>
                </tr>
            </thead>
            <tbody>
                {{KALEMLER_TABLOSU}}
            </tbody>
        </table>
    </section>

    <section class="eas-foot-grid">
        <div class="eas-notes-stack">
            <div class="eas-note-panel">
                <div class="eas-note-title">Notes</div>
                <div>{{NOTLAR}}</div>
            </div>
            <div class="eas-note-panel">
                <div class="eas-note-title">Terms & Conditions</div>
                <div>{{KOSULLAR}}</div>
            </div>
            <div class="eas-note-panel">
                <div class="eas-note-title">Payment Plan</div>
                <div>{{ODEME_PLANI}}</div>
            </div>
        </div>

        <div class="eas-totals">
            <div class="eas-total-row"><span>Sub Total</span><strong>{{ARA_TOPLAM}}</strong></div>
            <div class="eas-total-row"><span>Discount</span><strong>{{TOPLAM_INDIRIM}}</strong></div>
            <div class="eas-total-row"><span>VAT</span><strong>{{KDV_TOPLAM}}</strong></div>
            <div class="eas-total-row eas-grand"><span>Grand Total</span><strong>{{GENEL_TOPLAM}}</strong></div>
        </div>
    </section>
</div>
HTML;
    }

    private function privacySablonHtml(): string
    {
        $icerik = $this->hariciSablonHtml(
            'C:\Users\RogStrix\Downloads\privacy.html',
            [
                ['LOGO', '{{FIRMA_UNVAN}}'],
                ['Company Name', '{{MUSTERI_AD}}'],
                ['123 Grand Street, City, State', '{{MUSTERI_ADRES}}'],
                ['+01 234 567 890', '{{MUSTERI_TELEFON}}'],
                ['dd/mm/yy', '{{TEKLIF_TARIHI}}'],
                ['Lorem ipsum dolor sit amet, consectetur', '{{ODEME_PLANI}}'],
                ['adipiscing elit.', '{{KOSULLAR}}'],
                ['mail@website.com', '{{FIRMA_EPOSTA}}'],
                ['+01 234 56 78', '{{FIRMA_TELEFON}}'],
                ['Lorem Address, Lorem Ipsum', '{{FIRMA_ADRES}}'],
                ['Sit Amet', '{{NOTLAR}}'],
                ['DESCRIPTIONQTYPRICETOTAL', 'DESCRIPTIONQTYPRICETOTAL'],
                ['Lorem ipsum dolor sit 2$100$200', '{{KALEM_1_AD}} {{KALEM_1_MIKTAR}}{{KALEM_1_FIYAT}}{{KALEM_1_TOPLAM}}'],
                ['Lorem ipsum dolor sit 1$100$100', '{{KALEM_2_AD}} {{KALEM_2_MIKTAR}}{{KALEM_2_FIYAT}}{{KALEM_2_TOPLAM}}'],
                ['$50$50', '{{KALEM_3_FIYAT}}{{KALEM_3_TOPLAM}}'],
                ['$550', '{{ARA_TOPLAM}}'],
                ['$550', '{{GENEL_TOPLAM}}'],
                ['$0', '{{KDV_TOPLAM}}'],
                ['Sub Total', 'Ara Toplam'],
                ['Tax', 'KDV'],
                ['Total', 'Genel Toplam'],
            ]
        );

        $icerik = preg_replace('/<text class="f9" y="398\.6"[^>]*>DESCRIPTIONQTYPRICETOTAL<\/text>/', '{{KALEM_OVERLAY_PRIVACY}}', $icerik, 1) ?? $icerik;
        $icerik = preg_replace('/<text class="f1" y="465\.3"[^>]*>.*?<\/text>/', '', $icerik, 1) ?? $icerik;
        $icerik = preg_replace('/<text class="f1" y="533\.7"[^>]*>.*?<\/text>\s*<text class="f1" y="533\.7"[^>]*>1<\/text>\s*<text class="f1" y="533\.7"[^>]*>.*?<\/text>/', '', $icerik, 1) ?? $icerik;
        $icerik = preg_replace('/<text class="f1" y="602\.1"[^>]*>.*?<\/text>/', '', $icerik, 1) ?? $icerik;
        $icerik = preg_replace('/<text class="f1" y="670\.5"[^>]*>.*?<\/text>/', '', $icerik, 1) ?? $icerik;
        $icerik = preg_replace('/<text class="f8" y="730\.5"[^>]*>.*?<\/text>\s*<text class="f7" y="730\.5"[^>]*>Sub Total<\/text>\s*<text class="f8" y="782\.9"[^>]*>.*?<\/text>\s*<text class="f7" y="782\.9"[^>]*>Total<\/text>\s*<text class="f8" y="755\.9"[^>]*>.*?<\/text>\s*<text class="f7" y="755\.9"[^>]*>Tax<\/text>/', '{{TOPLAM_OVERLAY_PRIVACY}}', $icerik, 1) ?? $icerik;

        return $this->hariciSablonKalemStiliEkle($icerik, 'privacy');
    }

    private function technologySablonHtml(): string
    {
        $icerik = $this->hariciSablonHtml(
            'C:\Users\RogStrix\Downloads\technology.html',
            [
                ['@username', '@{{FIRMA_UNVAN}}'],
                ['Name surname', '{{MUSTERI_AD}}'],
                ['Street 123. California', '{{MUSTERI_ADRES}}'],
                ['February 13, 2023', '{{TEKLIF_TARIHI}}'],
                ['#0001', '{{TEKLIF_NO}}'],
                ['Lorem ipsum dolor', '{{KALEM_1_AD}}'],
                ['Consectetuer adipiscing elit', '{{KALEM_2_AD}}'],
                ['Sed diam nonummy nibh', '{{KALEM_3_AD}}'],
                ['Euismod tincidunt ut laoreet', '{{KALEM_4_AD}}'],
                ['Dolore magna aliquam', '{{KALEM_5_AD}}'],
                ['Minim veniam quis nostrud', '{{KALEM_6_AD}}'],
                ['$00.001$00.00', '{{KALEM_1_FIYAT}}{{KALEM_1_TOPLAM}}'],
                ['$00.001$00.00', '{{KALEM_2_FIYAT}}{{KALEM_2_TOPLAM}}'],
                ['$00.001$00.00', '{{KALEM_3_FIYAT}}{{KALEM_3_TOPLAM}}'],
                ['$00.001$00.00', '{{KALEM_4_FIYAT}}{{KALEM_4_TOPLAM}}'],
                ['$00.00$00.00', '{{KALEM_5_FIYAT}}{{KALEM_5_TOPLAM}}'],
                ['$00.001$00.00', '{{KALEM_6_FIYAT}}{{KALEM_6_TOPLAM}}'],
                ['$130.00', '{{ARA_TOPLAM}}'],
                ['$10.00Tax', '{{KDV_TOPLAM}}Tax'],
                ['Total$150.00', 'Total{{GENEL_TOPLAM}}'],
            ]
        );

        $icerik = preg_replace('/<text class="f4" y="378\.6"[^>]*>DescriptionPriceQty\.Total<\/text>/', '{{KALEM_OVERLAY_TECHNOLOGY}}', $icerik, 1) ?? $icerik;
        $icerik = preg_replace('/<text class="f1" y="436\.7"[^>]*>.*?<\/text>\s*<text class="f0" y="436\.7"[^>]*>.*?<\/text>/', '', $icerik, 1) ?? $icerik;
        $icerik = preg_replace('/<text class="f1" y="485\.8"[^>]*>.*?<\/text>\s*<text class="f0" y="485\.8"[^>]*>.*?<\/text>/', '', $icerik, 1) ?? $icerik;
        $icerik = preg_replace('/<text class="f1" y="534\.9"[^>]*>.*?<\/text>\s*<text class="f0" y="534\.9"[^>]*>.*?<\/text>/', '', $icerik, 1) ?? $icerik;
        $icerik = preg_replace('/<text class="f1" y="583\.9"[^>]*>.*?<\/text>\s*<text class="f0" y="583\.9"[^>]*>.*?<\/text>/', '', $icerik, 1) ?? $icerik;
        $icerik = preg_replace('/<text class="f1" y="633"[^>]*>.*?<\/text>\s*<text class="f0" y="633"[^>]*>1<\/text>\s*<text class="f0" y="631\.9"[^>]*>.*?<\/text>/', '', $icerik, 1) ?? $icerik;
        $icerik = preg_replace('/<text class="f1" y="682\.1"[^>]*>.*?<\/text>\s*<text class="f0" y="682\.1"[^>]*>.*?<\/text>/', '', $icerik, 1) ?? $icerik;
        $icerik = preg_replace('/<text class="f2" y="844"[^>]*>.*?<\/text>\s*<text class="f3" y="760"[^>]*>Subtotal<\/text>\s*<text class="f3" y="802"[^>]*>.*?<\/text>\s*<text class="f3" y="760"[^>]*>.*?<\/text>/', '{{TOPLAM_OVERLAY_TECHNOLOGY}}', $icerik, 1) ?? $icerik;
        $icerik = str_replace('</div>', $this->technologyGuvenlikKamerasiOverlay().'</div>', $icerik);

        return $this->hariciSablonKalemStiliEkle($icerik, 'technology');
    }

    private function consultancySablonHtml(): string
    {
        $icerik = $this->hariciSablonHtml(
            'C:\Users\RogStrix\Downloads\consultancy.html',
            [
                ['www.website.com', '{{FIRMA_UNVAN}}'],
                ['email@website.com', '{{FIRMA_EPOSTA}}'],
                ['+90 123 456 789', '{{FIRMA_TELEFON}}'],
                ['Street name Location', '{{FIRMA_ADRES}}'],
                ['John Brown', '{{MUSTERI_AD}}'],
                ['Lorem ipsum dolor sit amet', '{{MUSTERI_ADRES}}'],
                ['Date : 24/08/2030​', 'Date : {{TEKLIF_TARIHI}}'],
                ['Invoice: 0123456', 'Invoice: {{TEKLIF_NO}}'],
                ['Lorem ipsum dolor sit amet,', '{{KOSULLAR}}'],
                ['consecdetuer adispiscing elit.', '{{NOTLAR}}'],
                ['1Lorem ipsum dolor sit$ 100,00$ 100,00', '{{KALEM_1_MIKTAR}}{{KALEM_1_AD}}{{KALEM_1_FIYAT}}{{KALEM_1_TOPLAM}}'],
                ['1Lorem ipsum dolor sit$ 100,00$ 100,00', '{{KALEM_2_MIKTAR}}{{KALEM_2_AD}}{{KALEM_2_FIYAT}}{{KALEM_2_TOPLAM}}'],
                ['1Lorem ipsum dolor sit$ 100,00$ 100,00', '{{KALEM_3_MIKTAR}}{{KALEM_3_AD}}{{KALEM_3_FIYAT}}{{KALEM_3_TOPLAM}}'],
                ['1Lorem ipsum dolor sit$ 100,00$ 100,00', '{{KALEM_4_MIKTAR}}{{KALEM_4_AD}}{{KALEM_4_FIYAT}}{{KALEM_4_TOPLAM}}'],
                ['$ 400,00', '{{ARA_TOPLAM}}'],
                ['$ 400,00', '{{KDV_TOPLAM}}'],
                ['$ 800,00', '{{GENEL_TOPLAM}}'],
            ]
        );

        $icerik = preg_replace('/<text class="f6" y="425\.8"[^>]*>QtyDescriptionPriceTotal<\/text>/', '{{KALEM_OVERLAY_CONSULTANCY}}', $icerik, 1) ?? $icerik;
        $icerik = preg_replace('/<text class="f7" y="480\.9"[^>]*>.*?<\/text>/', '', $icerik, 1) ?? $icerik;
        $icerik = preg_replace('/<text class="f7" y="525\.6"[^>]*>.*?<\/text>/', '', $icerik, 1) ?? $icerik;
        $icerik = preg_replace('/<text class="f7" y="571\.2"[^>]*>.*?<\/text>/', '', $icerik, 1) ?? $icerik;
        $icerik = preg_replace('/<text class="f7" y="615\.9"[^>]*>.*?<\/text>/', '', $icerik, 1) ?? $icerik;
        $icerik = preg_replace('/<text class="f3" y="682\.4"[^>]*>.*?<\/text>\s*<text class="f9" y="682\.4"[^>]*>Sub Total<\/text>\s*<text class="f3" y="718\.4"[^>]*>.*?<\/text>\s*<text class="f9" y="718\.4"[^>]*>Tax<\/text>\s*<text class="f10" y="757\.5"[^>]*>.*?<\/text>\s*<text class="f9" y="757\.5"[^>]*>Total<\/text>/', '{{TOPLAM_OVERLAY_CONSULTANCY}}', $icerik, 1) ?? $icerik;

        return $this->hariciSablonKalemStiliEkle($icerik, 'consultancy');
    }

    private function pcSablonHtml(): string
    {
        $arkaPlanUrl = $this->publicAssetUrl('images/teklif-sablonlari/pc-teklif-a4-referans.png');

        return <<<HTML
<div class="pc-quote-sheet">
    <img class="pc-quote-sheet__bg" src="{$arkaPlanUrl}" alt="PC teklif şablonu arka planı">

    <div class="pc-mask pc-mask--musteri"></div>
    <div class="pc-mask pc-mask--teklif"></div>
    <div class="pc-mask pc-mask--tablo"></div>
    <div class="pc-mask pc-mask--notlar"></div>
    <div class="pc-mask pc-mask--toplamlar"></div>

    <div class="pc-field pc-field--musteri-ad">{{MUSTERI_AD}}</div>
    <div class="pc-field pc-field--musteri-vergi">{{MUSTERI_VERGI_TC}}</div>
    <div class="pc-field pc-field--musteri-adres">{{MUSTERI_ADRES}}</div>
    <div class="pc-field pc-field--musteri-telefon">{{MUSTERI_TELEFON}}</div>
    <div class="pc-field pc-field--musteri-eposta">{{MUSTERI_EPOSTA}}</div>

    <div class="pc-field pc-field--teklif-no">{{TEKLIF_NO}}</div>
    <div class="pc-field pc-field--teklif-tarih">{{TEKLIF_TARIHI}}</div>
    <div class="pc-field pc-field--teklif-gecerlilik">{{GECERLILIK_TARIHI}}</div>
    <div class="pc-field pc-field--teklif-musteri">{{MUSTERI_AD}}</div>
    <div class="pc-field pc-field--teklif-odeme">{{ODEME_PLANI}}</div>
    <div class="pc-field pc-field--teklif-hazirlayan">{{FIRMA_UNVAN}}</div>
    <div class="pc-field pc-field--teklif-teslim">{{TESLIM_SURESI}}</div>

    <div class="pc-row pc-row--1">
        <div class="pc-cell pc-cell--no">1</div>
        <div class="pc-cell pc-cell--urun">{{KALEM_1_AD}}</div>
        <div class="pc-cell pc-cell--adet">{{KALEM_1_MIKTAR}}</div>
        <div class="pc-cell pc-cell--fiyat">{{KALEM_1_FIYAT}}</div>
        <div class="pc-cell pc-cell--toplam">{{KALEM_1_TOPLAM}}</div>
    </div>
    <div class="pc-row pc-row--2">
        <div class="pc-cell pc-cell--no">2</div>
        <div class="pc-cell pc-cell--urun">{{KALEM_2_AD}}</div>
        <div class="pc-cell pc-cell--adet">{{KALEM_2_MIKTAR}}</div>
        <div class="pc-cell pc-cell--fiyat">{{KALEM_2_FIYAT}}</div>
        <div class="pc-cell pc-cell--toplam">{{KALEM_2_TOPLAM}}</div>
    </div>
    <div class="pc-row pc-row--3">
        <div class="pc-cell pc-cell--no">3</div>
        <div class="pc-cell pc-cell--urun">{{KALEM_3_AD}}</div>
        <div class="pc-cell pc-cell--adet">{{KALEM_3_MIKTAR}}</div>
        <div class="pc-cell pc-cell--fiyat">{{KALEM_3_FIYAT}}</div>
        <div class="pc-cell pc-cell--toplam">{{KALEM_3_TOPLAM}}</div>
    </div>
    <div class="pc-row pc-row--4">
        <div class="pc-cell pc-cell--no">4</div>
        <div class="pc-cell pc-cell--urun">{{KALEM_4_AD}}</div>
        <div class="pc-cell pc-cell--adet">{{KALEM_4_MIKTAR}}</div>
        <div class="pc-cell pc-cell--fiyat">{{KALEM_4_FIYAT}}</div>
        <div class="pc-cell pc-cell--toplam">{{KALEM_4_TOPLAM}}</div>
    </div>
    <div class="pc-row pc-row--5">
        <div class="pc-cell pc-cell--no">5</div>
        <div class="pc-cell pc-cell--urun">{{KALEM_5_AD}}</div>
        <div class="pc-cell pc-cell--adet">{{KALEM_5_MIKTAR}}</div>
        <div class="pc-cell pc-cell--fiyat">{{KALEM_5_FIYAT}}</div>
        <div class="pc-cell pc-cell--toplam">{{KALEM_5_TOPLAM}}</div>
    </div>
    <div class="pc-row pc-row--6">
        <div class="pc-cell pc-cell--no">6</div>
        <div class="pc-cell pc-cell--urun">{{KALEM_6_AD}}</div>
        <div class="pc-cell pc-cell--adet">{{KALEM_6_MIKTAR}}</div>
        <div class="pc-cell pc-cell--fiyat">{{KALEM_6_FIYAT}}</div>
        <div class="pc-cell pc-cell--toplam">{{KALEM_6_TOPLAM}}</div>
    </div>
    <div class="pc-row pc-row--7">
        <div class="pc-cell pc-cell--no">7</div>
        <div class="pc-cell pc-cell--urun">{{KALEM_7_AD}}</div>
        <div class="pc-cell pc-cell--adet">{{KALEM_7_MIKTAR}}</div>
        <div class="pc-cell pc-cell--fiyat">{{KALEM_7_FIYAT}}</div>
        <div class="pc-cell pc-cell--toplam">{{KALEM_7_TOPLAM}}</div>
    </div>
    <div class="pc-row pc-row--8">
        <div class="pc-cell pc-cell--no">8</div>
        <div class="pc-cell pc-cell--urun">{{KALEM_8_AD}}</div>
        <div class="pc-cell pc-cell--adet">{{KALEM_8_MIKTAR}}</div>
        <div class="pc-cell pc-cell--fiyat">{{KALEM_8_FIYAT}}</div>
        <div class="pc-cell pc-cell--toplam">{{KALEM_8_TOPLAM}}</div>
    </div>

    <div class="pc-field pc-field--not-1">• {{KOSULLAR}}</div>
    <div class="pc-field pc-field--not-2">• {{NOTLAR}}</div>
    <div class="pc-field pc-field--not-3">• {{TEKLIF_ACIKLAMA}}</div>

    <div class="pc-field pc-field--ara-toplam">{{ARA_TOPLAM}}</div>
    <div class="pc-field pc-field--kdv-toplam">{{KDV_TOPLAM}}</div>
    <div class="pc-field pc-field--genel-toplam">{{GENEL_TOPLAM}}</div>
</div>
HTML;
    }

    /**
     * @param  array<int, array{0:string,1:string}>  $degisimler
     */
    private function hariciSablonHtml(string $dosyaYolu, array $degisimler): string
    {
        $icerik = @file_get_contents($dosyaYolu);

        if ($icerik === false || trim($icerik) === '') {
            return '<div style="font-size:14px;color:#64748b;">Kaynak şablon dosyası bulunamadı: '.e($dosyaYolu).'</div>';
        }

        foreach ($degisimler as [$aranan, $yeni]) {
            $icerik = Str::replaceFirst($aranan, $yeni, $icerik);
        }

        return $icerik;
    }

    private function hariciSablonKalemStiliEkle(string $icerik, string $sablon): string
    {
        $stil = match ($sablon) {
            'privacy' => <<<'CSS'
<style>
.teklif-overlay{position:absolute;z-index:6;box-sizing:border-box;color:#264da7}
.teklif-overlay *{box-sizing:border-box}
.teklif-overlay--privacy{top:392px;left:73px;width:634px;font-family:"DejaVu Sans","Segoe UI",Arial,sans-serif}
.teklif-overlay--privacy .teklif-overlay__head,
.teklif-overlay--privacy .teklif-overlay__row{display:grid;grid-template-columns:70px minmax(0,1fr) 120px 120px;column-gap:18px;align-items:start}
.teklif-overlay--privacy .teklif-overlay__head{font-size:12px;font-weight:700;letter-spacing:.08em;margin-bottom:14px}
.teklif-overlay--privacy .teklif-overlay__body{display:grid;row-gap:18px}
.teklif-overlay--privacy .teklif-overlay__row{font-size:13px;line-height:1.4;min-height:50px}
.teklif-total-overlay--privacy{top:719px;left:548px;width:162px;color:#264da7}
.teklif-total-overlay--privacy .teklif-total-overlay__row{font-size:13px;line-height:1.25;margin-bottom:10px}
.teklif-total-overlay--privacy .teklif-total-overlay__row--grand{margin-top:4px}
</style>
CSS,
            'technology' => <<<'CSS'
<style>
.teklif-overlay{position:absolute;z-index:6;box-sizing:border-box;color:#222324}
.teklif-overlay *{box-sizing:border-box}
.teklif-overlay--technology{top:373px;left:93px;width:592px;font-family:"DejaVu Sans","Segoe UI",Arial,sans-serif}
.teklif-overlay--technology .teklif-overlay__head,
.teklif-overlay--technology .teklif-overlay__row{display:grid;grid-template-columns:minmax(0,1fr) 120px 68px 120px;column-gap:16px;align-items:start}
.teklif-overlay--technology .teklif-overlay__head{font-size:14px;font-weight:700;letter-spacing:.02em;margin-bottom:16px}
.teklif-overlay--technology .teklif-overlay__body{display:grid;row-gap:18px}
.teklif-overlay--technology .teklif-overlay__row{font-size:13px;line-height:1.45;min-height:31px}
.teklif-total-overlay--technology{top:748px;left:503px;width:211px;color:#222324}
.teklif-total-overlay--technology .teklif-total-overlay__row{font-size:13px;line-height:1.3;margin-bottom:14px}
.teklif-total-overlay--technology .teklif-total-overlay__row--grand{font-size:15px;margin-top:6px}
.technology-camera-replacement{position:absolute;left:34px;bottom:44px;width:182px;height:182px;border-radius:36px;background:#ffffff;z-index:9999;display:flex;align-items:center;justify-content:center;box-shadow:none}
.technology-camera-replacement::before{content:"";position:absolute;inset:0;border-radius:36px;background:#ffffff}
.technology-camera-replacement svg{position:relative;width:112px;height:112px;display:block}
</style>
CSS,
            default => <<<'CSS'
<style>
.teklif-overlay{position:absolute;z-index:6;box-sizing:border-box;color:#264da7}
.teklif-overlay *{box-sizing:border-box}
.teklif-overlay--consultancy{top:419px;left:126px;width:552px;font-family:"DejaVu Sans","Segoe UI",Arial,sans-serif}
.teklif-overlay--consultancy .teklif-overlay__head,
.teklif-overlay--consultancy .teklif-overlay__row{display:grid;grid-template-columns:60px minmax(0,1fr) 115px 115px;column-gap:14px;align-items:start}
.teklif-overlay--consultancy .teklif-overlay__head{font-size:12px;font-weight:700;letter-spacing:.06em;margin-bottom:14px}
.teklif-overlay--consultancy .teklif-overlay__body{display:grid;row-gap:18px}
.teklif-overlay--consultancy .teklif-overlay__row{font-size:13px;line-height:1.42;min-height:27px}
.teklif-total-overlay--consultancy{top:671px;left:482px;width:180px;color:#264da7}
.teklif-total-overlay--consultancy .teklif-total-overlay__row{font-size:13px;line-height:1.25;margin-bottom:12px}
.teklif-total-overlay--consultancy .teklif-total-overlay__row--grand{font-size:15px;margin-top:4px}
</style>
CSS,
        };

        $ortakStil = <<<'CSS'
<style>
.teklif-overlay__cell{display:block;min-width:0}
.teklif-overlay__cell--qty{text-align:center;white-space:nowrap}
.teklif-overlay__cell--money{text-align:right;white-space:nowrap}
.teklif-overlay__cell--desc{white-space:normal;overflow-wrap:anywhere;word-break:break-word}
.teklif-overlay__cell--head{white-space:nowrap}
.teklif-total-overlay{position:absolute;z-index:6;box-sizing:border-box;font-family:"DejaVu Sans","Segoe UI",Arial,sans-serif}
.teklif-total-overlay *{box-sizing:border-box}
.teklif-total-overlay__row{display:grid;grid-template-columns:minmax(0,1fr) max-content;column-gap:18px;align-items:center}
.teklif-total-overlay__row span{white-space:nowrap}
.teklif-total-overlay__row strong{text-align:right;white-space:nowrap;max-width:100%}
.teklif-total-overlay__row--grand strong{font-weight:700}
</style>
CSS;

        $stilBlogu = $ortakStil.$stil;

        return preg_replace('/<\/style>/', '</style>'.$stilBlogu, $icerik, 1) ?? ($stilBlogu.$icerik);
    }

    private function technologyGuvenlikKamerasiOverlay(): string
    {
        return <<<'HTML'
<div class="technology-camera-replacement" aria-hidden="true">
    <svg viewBox="0 0 96 96" xmlns="http://www.w3.org/2000/svg" role="img">
        <rect x="18" y="24" width="42" height="24" rx="8" fill="#264da7"/>
        <path d="M58 30l16-7c4-2 8 1 8 5v16c0 4-4 7-8 5l-16-7V30z" fill="#3f6ebe"/>
        <circle cx="39" cy="36" r="7" fill="#ffffff"/>
        <circle cx="39" cy="36" r="3.5" fill="#f6901d"/>
        <path d="M30 54h18l8 10h-9v8H38v-8H22l8-10z" fill="#264da7"/>
        <rect x="34" y="72" width="18" height="6" rx="3" fill="#f6901d"/>
    </svg>
</div>
HTML;
    }

    private function cssA4(): string
    {
        return <<<'CSS'
body { font-family: DejaVu Sans, Arial, sans-serif; color: #0f172a; }
.teklif-wrap { padding: 18px; }
.teklif-header { display: flex; justify-content: space-between; gap: 24px; margin-bottom: 18px; }
.teklif-brand { display: flex; gap: 16px; align-items: flex-start; }
.teklif-logo img { max-height: 72px; max-width: 160px; }
.teklif-eyebrow { font-size: 12px; letter-spacing: .12em; color: #64748b; margin-bottom: 6px; }
.teklif-firma h1 { margin: 0 0 6px; font-size: 24px; }
.teklif-meta { min-width: 220px; background: #f8fafc; border: 1px solid #dbe3ee; border-radius: 14px; padding: 14px; }
.teklif-meta div + div { margin-top: 8px; }
.teklif-section { margin-top: 16px; }
.teklif-section-title { font-size: 12px; font-weight: 700; letter-spacing: .08em; color: #475569; text-transform: uppercase; margin-bottom: 8px; }
.teklif-grid { display: grid; gap: 10px; }
.teklif-grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.teklif-subject { border: 1px solid #dbe3ee; border-radius: 12px; padding: 12px 14px; background: #fff; }
.teklif-table { width: 100%; border-collapse: collapse; }
.teklif-table th, .teklif-table td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; text-align: left; }
.teklif-table thead th { background: #f8fafc; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: #475569; }
.teklif-total-box { margin-top: 18px; margin-left: auto; width: 320px; border: 1px solid #dbe3ee; border-radius: 14px; padding: 14px; background: #f8fafc; }
.teklif-total-box div { display: flex; justify-content: space-between; gap: 16px; padding: 6px 0; }
.teklif-total-box .grand { margin-top: 8px; padding-top: 10px; border-top: 1px solid #cbd5e1; font-size: 16px; }
.teklif-notes { margin-top: 20px; display: grid; gap: 14px; }
.note-block { border: 1px solid #dbe3ee; border-radius: 12px; padding: 12px 14px; }
CSS;
    }

    private function cssYalovaBilgisayarA4(): string
    {
        return <<<'CSS'
.yb-offer-page {
    box-sizing: border-box;
    width: 100%;
    max-width: none;
    min-height: 297mm;
    height: auto;
    margin: 0 auto;
    padding: 0;
    background: #fff;
    color: #1b2a3c;
    font-family: "DejaVu Sans", "Segoe UI", Arial, sans-serif;
    overflow: visible;
}

.yb-offer-header {
    display: grid;
    grid-template-columns: minmax(0, 1.82fr) minmax(64mm, 1fr);
    grid-template-rows: auto auto;
    column-gap: 6mm;
    row-gap: 1.8mm;
    align-items: stretch;
    padding: 0;
    margin-bottom: 2.8mm;
}

.yb-offer-brand {
    grid-column: 1;
    grid-row: 1;
    position: relative;
    min-height: 32mm;
    padding: 5.4mm 7mm 5.4mm 8mm;
    overflow: hidden;
    background: #101317;
    clip-path: polygon(0 0, 88% 0, 100% 54%, 88% 100%, 0 100%);
}

.yb-offer-brand::after {
    content: "";
    position: absolute;
    left: 0;
    right: 6%;
    bottom: 0;
    height: 4mm;
    background: #157fe8;
    clip-path: polygon(0 0, 100% 0, 94% 100%, 0 100%);
}

.yb-offer-brand__inner {
    display: flex;
    align-items: center;
    gap: 4mm;
}

.yb-offer-mark {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30mm;
    flex: 0 0 30mm;
}

.yb-offer-mark img {
    display: block;
    width: 100%;
    max-width: 30mm;
    height: auto;
}

.yb-offer-brand__copy strong,
.yb-offer-brand__copy span,
.yb-offer-brand__copy em {
    display: block;
    font-style: normal;
    line-height: 0.92;
}

.yb-offer-brand__copy strong {
    color: #fff;
    font-size: 11.8mm;
    font-weight: 700;
}

.yb-offer-brand__copy span {
    color: #1982ec;
    font-size: 16.6mm;
    font-weight: 800;
    letter-spacing: -0.05em;
}

.yb-offer-brand__copy em {
    display: inline-block;
    margin-top: 0.8mm;
    margin-left: 0;
    padding-left: 28ch;
    color: #ef2d28;
    font-size: 4.5mm;
    font-weight: 700;
}

.yb-offer-contact {
    grid-column: 2;
    grid-row: 1 / span 2;
    align-self: stretch;
    min-height: 46mm;
    padding: 2mm 0 0;
    overflow: visible;
}

.yb-offer-contact__item {
    display: flex;
    align-items: center;
    gap: 1.8mm;
    margin-bottom: 1.8mm;
    font-size: 3.7mm;
    line-height: 1.12;
    font-weight: 600;
}

.yb-offer-contact__text {
    display: flex;
    align-items: center;
    min-height: 6.4mm;
    flex: 1 1 auto;
    white-space: normal;
    overflow-wrap: anywhere;
}

.yb-offer-contact__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 6.4mm;
    width: 6.4mm;
    height: 6.4mm;
    border-radius: 1.4mm;
    background: linear-gradient(180deg, #0d4ca5 0%, #09367a 100%);
    color: #fff;
    font-size: 3.2mm;
}

.yb-offer-body {
    padding: 0;
}

.yb-offer-title {
    grid-column: 1;
    grid-row: 2;
    margin: 0 0 0 1mm;
    padding-top: 1.2mm;
    color: #05070a;
    font-size: 14.2mm;
    line-height: 1.02;
    font-weight: 800;
    letter-spacing: -0.04em;
    overflow: visible;
}

.yb-offer-title span {
    color: #0d4ca5;
}

.yb-offer-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4mm;
    margin-bottom: 2.8mm;
}

.yb-offer-panel {
    background: #fbfcfe;
    border: 0.2mm solid rgba(6, 35, 78, 0.08);
    border-radius: 4mm;
    padding: 3.2mm 3.8mm 2.4mm;
}

.yb-offer-panel__label {
    display: inline-block;
    min-width: 56mm;
    margin: -1.8mm 0 2.2mm;
    padding: 1.5mm 2.5mm;
    border-radius: 2.4mm;
    background: linear-gradient(180deg, #0d4ca5 0%, #09367a 100%);
    color: #fff;
    font-size: 3.6mm;
    font-weight: 700;
    text-align: center;
}

.yb-offer-fields {
    display: grid;
    gap: 1.5mm;
}

.yb-offer-field {
    display: grid;
    grid-template-columns: 30mm 3mm 1fr;
    gap: 1.2mm;
    align-items: center;
    font-size: 3.45mm;
}

.yb-offer-field strong {
    font-weight: 700;
}

.yb-offer-line {
    display: flex;
    align-items: center;
    min-height: 4.8mm;
    padding-bottom: 0;
    border-bottom: 0.45mm solid rgba(28, 38, 53, 0.36);
    font-weight: 600;
    line-height: 1.16;
    white-space: normal;
    overflow-wrap: anywhere;
}

.yb-offer-field--multiline .yb-offer-line {
    min-height: 10mm;
}

.yb-offer-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: auto;
    border: 0.25mm solid rgba(16, 53, 109, 0.16);
}

.yb-offer-table th {
    padding: 2.2mm 1.4mm;
    background: linear-gradient(180deg, #0d4ca5 0%, #09367a 100%);
    color: #fff;
    font-size: 3.55mm;
    font-weight: 700;
    text-align: center;
    border-right: 0.25mm solid rgba(255, 255, 255, 0.2);
    white-space: nowrap;
}

.yb-offer-table td {
    height: 7.35mm;
    padding: 1mm 1.4mm;
    border-top: 0.25mm solid rgba(16, 53, 109, 0.10);
    border-right: 0.25mm solid rgba(16, 53, 109, 0.10);
    font-size: 3.45mm;
    vertical-align: middle;
    white-space: nowrap;
}

.yb-offer-table th:last-child,
.yb-offer-table td:last-child {
    border-right: 0;
}

.yb-offer-table tbody td:nth-child(1) {
    width: 1%;
    text-align: center;
    font-weight: 700;
}

.yb-offer-table th:nth-child(1) {
    font-size: 2.8mm;
    line-height: 1.02;
    white-space: normal;
}

.yb-offer-table tbody td:nth-child(2) {
    width: auto;
    white-space: normal;
}

.yb-offer-table tbody td:nth-child(3) {
    width: 1%;
    text-align: center;
}

.yb-offer-table tbody td:nth-child(4),
.yb-offer-table tbody td:nth-child(5) {
    width: 1%;
    text-align: right;
}

.yb-offer-bottom-grid {
    display: grid;
    grid-template-columns: 1.08fr 0.92fr;
    gap: 4.5mm;
    margin-top: 4mm;
    align-items: start;
}

.yb-offer-notes,
.yb-offer-summary {
    background: #fbfcfe;
    border: 0.2mm solid rgba(6, 35, 78, 0.08);
    border-radius: 4mm;
}

.yb-offer-notes {
    padding: 2.6mm 3.6mm;
}

.yb-offer-section-title {
    margin-bottom: 1.6mm;
    color: #0d4ca5;
    font-size: 4.25mm;
    font-weight: 800;
}

.yb-offer-notes ul {
    margin: 0;
    padding-left: 4mm;
}

.yb-offer-notes li {
    margin-bottom: 1mm;
    font-size: 3.45mm;
    line-height: 1.18;
}

.yb-offer-notes__footer {
    margin-top: 2mm;
    padding-top: 1.6mm;
    border-top: 0.25mm solid rgba(16, 53, 109, 0.10);
}

.yb-offer-warranty {
    display: grid;
    grid-template-columns: 16mm 1fr;
    gap: 2mm;
    align-items: center;
    margin-top: 2mm;
    padding-top: 2mm;
    border-top: 0.25mm solid rgba(16, 53, 109, 0.10);
}

.yb-offer-warranty__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 12mm;
    height: 12mm;
    border-radius: 2mm;
    background: linear-gradient(180deg, #0d4ca5 0%, #09367a 100%);
    color: #fff;
    font-size: 5.8mm;
}

.yb-offer-warranty p {
    margin: 0;
    font-size: 3.45mm;
    line-height: 1.2;
}

.yb-offer-warranty strong {
    color: #0d4ca5;
}

.yb-offer-summary {
    overflow: hidden;
}

.yb-offer-summary table {
    width: 100%;
    border-collapse: collapse;
}

.yb-offer-summary td {
    padding: 2.4mm 3.6mm;
    font-size: 3.95mm;
    font-weight: 700;
    border-bottom: 0.25mm solid rgba(16, 53, 109, 0.12);
}

.yb-offer-summary td:last-child {
    width: 34mm;
    text-align: right;
}

.yb-offer-summary__total td {
    background: linear-gradient(180deg, #0d4ca5 0%, #09367a 100%);
    color: #fff;
    font-size: 4.25mm;
    border-bottom: 0;
}

.yb-offer-sign-row {
    display: none;
}

.yb-offer-thanks__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 11mm;
    height: 11mm;
    border-radius: 50%;
    background: linear-gradient(180deg, #0d4ca5 0%, #09367a 100%);
    color: #fff;
    font-size: 5.2mm;
}

.yb-offer-thanks p {
    margin: 0;
    font-size: 3.7mm;
    line-height: 1.12;
    font-style: italic;
    font-family: "DejaVu Serif", Georgia, serif;
    color: #364259;
}

.yb-offer-thanks strong {
    display: block;
    margin-top: 0.5mm;
    color: #0d4ca5;
    font-size: 4.45mm;
}

.yb-offer-thanks {
    display: flex;
    align-items: center;
    gap: 2.4mm;
}

.yb-offer-summary__approval {
    padding: 3.8mm 3.6mm 3.4mm;
}

.yb-offer-approval h3,
.yb-offer-summary__approval h3 {
    margin: 0;
    color: #0d4ca5;
    font-size: 4.5mm;
    font-weight: 800;
}

.yb-offer-approval p,
.yb-offer-summary__approval p {
    margin: 1.5mm 0 4mm;
    font-size: 3.9mm;
}

.yb-offer-approval {
    width: 42%;
    min-width: 64mm;
    padding-left: 0;
}

.yb-offer-signature {
    width: 100%;
    height: 9mm;
    border-bottom: 0.45mm solid rgba(28, 38, 53, 0.36);
}

.yb-offer-disclaimer {
    margin-top: 0;
    padding: 2mm 6mm;
    background: linear-gradient(90deg, #101317 0 64%, #0d4ca5 64% 100%);
    min-height: 6.5mm;
}

@media screen and (max-width: 1100px) {
    .yb-offer-page {
        width: 100%;
        min-height: auto;
    }

    .yb-offer-header,
    .yb-offer-info-grid,
    .yb-offer-bottom-grid,
    .yb-offer-sign-row {
        grid-template-columns: 1fr;
    }

    .yb-offer-contact,
    .yb-offer-title {
        grid-column: 1;
        grid-row: auto;
    }

    .yb-offer-sign-divider {
        display: none;
    }

    .yb-offer-brand {
        clip-path: none;
    }
}
CSS;
    }

    private function cssA5(): string
    {
        return $this->cssA4()."\n.teklif-wrap { padding: 12px; font-size: 12px; }\n.teklif-firma h1 { font-size: 18px; }\n.teklif-grid-3 { grid-template-columns: repeat(2, minmax(0, 1fr)); }\n.teklif-total-box { width: auto; }\n";
    }

    private function css80mm(): string
    {
        return <<<'CSS'
body { font-family: DejaVu Sans, Arial, sans-serif; color: #111827; font-size: 10px; }
.teklif-mini { padding: 6px; }
.mini-logo img { max-height: 28px; max-width: 100%; margin-bottom: 6px; }
.mini-title { font-size: 12px; font-weight: 700; margin-bottom: 6px; }
.mini-line { margin-bottom: 4px; }
.mini-total { margin-top: 8px; padding-top: 6px; border-top: 1px dashed #94a3b8; font-size: 12px; font-weight: 700; }
CSS;
    }

    private function cssEasA4(): string
    {
        return <<<'CSS'
body {
    font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
    color: #666666;
    font-size: 12px;
    line-height: 1.45;
    background: #ffffff;
}

.eas-sheet {
    width: 100%;
    padding: 26px 30px 28px;
    box-sizing: border-box;
}

.eas-topline {
    height: 2px;
    background: #cfcfcf;
    margin-bottom: 18px;
}

.eas-header {
    display: flex;
    justify-content: space-between;
    gap: 28px;
    align-items: flex-start;
    margin-bottom: 22px;
}

.eas-brand {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    flex: 1 1 auto;
    min-width: 0;
}

.eas-logo img {
    max-width: 110px;
    max-height: 72px;
    display: block;
}

.eas-brand-copy {
    min-width: 0;
}

.eas-kicker {
    font-size: 10px;
    letter-spacing: 0.18em;
    color: #8d8d8d;
    margin-bottom: 5px;
}

.eas-brand-copy h1 {
    margin: 0 0 6px;
    font-size: 26px;
    line-height: 1.05;
    font-weight: 500;
    color: #555555;
}

.eas-contact {
    margin-top: 2px;
}

.eas-quote-box {
    width: 230px;
    border: 1px solid #d9d9d9;
    padding: 10px 14px;
    box-sizing: border-box;
}

.eas-quote-row {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    padding: 4px 0;
    border-bottom: 1px solid #efefef;
}

.eas-quote-row:last-child {
    border-bottom: 0;
}

.eas-quote-row span {
    color: #888888;
}

.eas-quote-row strong {
    font-weight: 600;
    color: #4f4f4f;
}

.eas-block {
    margin-top: 16px;
}

.eas-block-title,
.eas-note-title {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.16em;
    color: #8a8a8a;
    margin-bottom: 8px;
}

.eas-customer-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.eas-customer-item,
.eas-subject,
.eas-note-panel {
    border: 1px solid #e3e3e3;
    padding: 10px 12px;
    box-sizing: border-box;
}

.eas-customer-item span {
    display: block;
    font-size: 10px;
    color: #8d8d8d;
    margin-bottom: 4px;
}

.eas-customer-item strong {
    color: #5a5a5a;
    font-weight: 600;
}

.eas-subject {
    min-height: 44px;
    color: #555555;
}

.eas-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.eas-table th,
.eas-table td {
    border: 1px solid #dcdcdc;
    padding: 8px 10px;
    vertical-align: top;
}

.eas-table thead th {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: #7d7d7d;
    background: #fafafa;
}

.eas-table .qty {
    width: 64px;
    text-align: center;
}

.eas-table .money {
    width: 120px;
    text-align: right;
}

.eas-table td:nth-child(2) {
    text-align: center;
}

.eas-table td:nth-child(3),
.eas-table td:nth-child(4) {
    text-align: right;
    white-space: nowrap;
}

.eas-foot-grid {
    margin-top: 20px;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 250px;
    gap: 20px;
    align-items: start;
}

.eas-notes-stack {
    display: grid;
    gap: 12px;
}

.eas-totals {
    border: 1px solid #d9d9d9;
    padding: 10px 14px;
    box-sizing: border-box;
}

.eas-total-row {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding: 6px 0;
    border-bottom: 1px solid #efefef;
}

.eas-total-row:last-child {
    border-bottom: 0;
}

.eas-total-row span {
    color: #8a8a8a;
}

.eas-total-row strong {
    color: #555555;
    font-weight: 600;
}

.eas-grand {
    margin-top: 6px;
    padding-top: 10px;
    border-top: 1px solid #d4d4d4;
}

.eas-grand span,
.eas-grand strong {
    color: #444444;
    font-size: 13px;
    font-weight: 700;
}
CSS;
    }

    private function cssPrivacyA4(): string
    {
        return '';
    }

    private function cssTechnologyA4(): string
    {
        return '';
    }

    private function cssConsultancyA4(): string
    {
        return '';
    }

    private function cssPcA4(): string
    {
        return <<<'CSS'
body {
    font-family: "DejaVu Sans", "Segoe UI", Arial, sans-serif;
    background: #ffffff;
    color: #173765;
}

.pc-quote-sheet {
    position: relative;
    width: 864px;
    height: 1184px;
    margin: 0 auto;
    overflow: hidden;
    background: #ffffff;
}

.pc-quote-sheet__bg {
    position: absolute;
    inset: 0;
    width: 864px;
    height: 1184px;
    display: block;
}

.pc-mask,
.pc-field,
.pc-row {
    position: absolute;
    box-sizing: border-box;
}

.pc-mask {
    z-index: 2;
    background: rgba(255, 255, 255, 0.94);
}

.pc-mask--musteri {
    left: 88px;
    top: 379px;
    width: 288px;
    height: 184px;
}

.pc-mask--teklif {
    left: 441px;
    top: 379px;
    width: 261px;
    height: 184px;
}

.pc-mask--tablo {
    left: 70px;
    top: 603px;
    width: 617px;
    height: 224px;
}

.pc-mask--notlar {
    left: 78px;
    top: 816px;
    width: 290px;
    height: 98px;
}

.pc-mask--toplamlar {
    left: 552px;
    top: 806px;
    width: 136px;
    height: 88px;
}

.pc-field {
    z-index: 3;
    font-size: 17px;
    line-height: 1.15;
    color: #344055;
    white-space: nowrap;
    overflow: hidden;
}

.pc-field--musteri-ad { left: 120px; top: 406px; width: 221px; }
.pc-field--musteri-vergi { left: 120px; top: 442px; width: 221px; }
.pc-field--musteri-adres { left: 120px; top: 478px; width: 221px; }
.pc-field--musteri-telefon { left: 120px; top: 514px; width: 221px; }
.pc-field--musteri-eposta { left: 120px; top: 550px; width: 221px; }

.pc-field--teklif-no { left: 473px; top: 406px; width: 109px; }
.pc-field--teklif-tarih { left: 591px; top: 406px; width: 82px; }
.pc-field--teklif-gecerlilik { left: 473px; top: 442px; width: 200px; }
.pc-field--teklif-musteri { left: 473px; top: 478px; width: 109px; }
.pc-field--teklif-odeme { left: 591px; top: 478px; width: 82px; }
.pc-field--teklif-hazirlayan { left: 473px; top: 514px; width: 109px; }
.pc-field--teklif-teslim { left: 591px; top: 550px; width: 82px; }

.pc-row {
    z-index: 4;
    left: 70px;
    width: 617px;
    height: 27px;
    color: #24384f;
    font-size: 15px;
    line-height: 27px;
}

.pc-row--1 { top: 617px; }
.pc-row--2 { top: 645px; }
.pc-row--3 { top: 673px; }
.pc-row--4 { top: 701px; }
.pc-row--5 { top: 729px; }
.pc-row--6 { top: 757px; }
.pc-row--7 { top: 785px; }
.pc-row--8 { top: 813px; }

.pc-cell {
    position: absolute;
    top: 0;
    height: 27px;
    overflow: hidden;
    white-space: nowrap;
}

.pc-cell--no {
    left: 7px;
    width: 38px;
    text-align: center;
}

.pc-cell--urun {
    left: 49px;
    width: 253px;
    padding-left: 9px;
    text-align: left;
}

.pc-cell--adet {
    left: 305px;
    width: 126px;
    text-align: center;
}

.pc-cell--fiyat {
    left: 434px;
    width: 82px;
    text-align: center;
}

.pc-cell--toplam {
    left: 519px;
    width: 93px;
    text-align: center;
}

.pc-field--not-1,
.pc-field--not-2,
.pc-field--not-3 {
    left: 97px;
    width: 252px;
    font-size: 15px;
    white-space: nowrap;
}

.pc-field--not-1 { top: 839px; }
.pc-field--not-2 { top: 864px; }
.pc-field--not-3 { top: 889px; }

.pc-field--ara-toplam,
.pc-field--kdv-toplam,
.pc-field--genel-toplam {
    left: 608px;
    width: 70px;
    text-align: center;
    color: #1f365d;
}

.pc-field--ara-toplam { top: 827px; font-size: 13px; }
.pc-field--kdv-toplam { top: 851px; font-size: 13px; }
.pc-field--genel-toplam {
    top: 878px;
    width: 121px;
    left: 562px;
    font-size: 20px;
    font-weight: 700;
}

@media print {
    body {
        background: #ffffff;
    }
}

@media screen and (max-width: 900px) {
    .pc-quote-sheet {
        transform-origin: top left;
        transform: scale(calc(100vw / 900));
    }
}
CSS;
    }

    private function firmaLogoUrl(int $firmaId): ?string
    {
        if ($firmaId < 1) {
            return null;
        }

        $logo = (string) (app(FirmaAyarDeposu::class)->oku($firmaId, 'logo', '') ?? '');

        return $this->logoUrlHazirla($logo);
    }

    private function logoUrlHazirla(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return $this->publicAssetUrl('storage/'.ltrim(str_replace('\\', '/', $path), '/'));
    }

    private function varsayilanLogoUrl(): string
    {
        return $this->publicAssetUrl('images/teklif-sablonlari/yb-logo.png');
    }

    private function publicAssetUrl(string $path): string
    {
        return asset(ltrim(str_replace('\\', '/', $path), '/'));
    }
}
