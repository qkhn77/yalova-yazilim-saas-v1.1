<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis;
use App\Filament\Clusters\TeknikServis\Concerns\TeknikServisKayitFormSchema;
use App\Models\Firma;
use App\Models\TeknikServis\TeknikServisBaskiSablonu;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Services\FirmaAyarDeposu;
use App\Services\TenantContextService;
use App\TeknikServis\Servisler\TeknikServisBaskiSablonuServisi;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class TeslimFisi extends Page
{
    private const DEFAULT_LOGO_PATH = 'teknik-servis-sablon-logolari/iV8V8hfdaQcYmGA4aF6QcjPweytbx7vZtweJFqso.png';

    protected static ?string $cluster = TeknikServis::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Teslim Fisi';

    protected static ?string $slug = 'ayarlar/teslim-fisi';

    protected static string $view = 'filament.clusters.teknik-servis.pages.teslim-fisi';

    public ?TeknikServisKaydi $kayit = null;

    public ?TeknikServisBaskiSablonu $sablon = null;

    public bool $otomatikYazdir = false;

    public function mount(): void
    {
        $this->otomatikYazdir = (bool) request()->boolean('auto_print', false);
        $kayitId = (int) request()->query('record', 0);

        if ($kayitId > 0) {
            $this->kayit = TeknikServisKaydi::query()
                ->with(['firma', 'cari', 'cihaz', 'marka', 'ariza', 'servisDurumu', 'kalemler.stok', 'tahsilatlar', 'aksesuarlar'])
                ->find($kayitId);
        }

        if ($this->kayit) {
            $this->sablon = $this->varsayilanTeslimSablonu();
        }
    }

    public function getHeading(): string|Htmlable
    {
        return 'Teslim Formu';
    }

    public function icerik(): Htmlable
    {
        if (! $this->kayit) {
            return new HtmlString('<div class="text-sm text-gray-700">Servis kaydi bulunamadi.</div>');
        }

        $html = trim((string) ($this->sablon?->sablon_html ?? ''));
        if ($html === '') {
            return new HtmlString('<div class="text-sm text-gray-700">Teslim formu sablonu bulunamadi.</div>');
        }

        return new HtmlString(strtr($html, $this->sablonVerileri()));
    }

    public function sayfaCss(): string
    {
        $sayfaTipi = (string) ($this->sablon?->sayfa_tipi ?? 'a4');
        $sayfaBoyutu = match ($sayfaTipi) {
            'a5' => 'A5',
            '80mm' => '80mm auto',
            '58mm' => '58mm auto',
            '10x10mm' => '10mm 10mm',
            default => 'A4',
        };

        $kenarBosluk = match ($sayfaTipi) {
            '58mm', '80mm' => '4mm',
            '10x10mm' => '0',
            default => '10mm',
        };

        return "@page { size: {$sayfaBoyutu}; margin: {$kenarBosluk}; }\n"
            .".servis-toplam, .sr80-summary, .teslim-tutar, [data-teslim-tutar] { display: none !important; }\n"
            .(string) ($this->sablon?->sablon_css ?? '');
    }

    public function belgeKapsayiciStili(): string
    {
        return match ((string) ($this->sablon?->sayfa_tipi ?? 'a4')) {
            'a4' => 'max-width: 210mm; min-height: 297mm;',
            'a5' => 'max-width: 148mm; min-height: 210mm;',
            '58mm' => 'max-width: 58mm; min-height: 100mm;',
            '10x10mm' => 'max-width: 10mm; min-height: 10mm; padding: 0;',
            default => 'max-width: 80mm; min-height: 100mm;',
        };
    }

    private function varsayilanTeslimSablonu(): ?TeknikServisBaskiSablonu
    {
        $firmaId = (int) ($this->kayit?->firma_id ?: $this->aktifFirmaId() ?: 0);
        if ($firmaId < 1) {
            return null;
        }

        $servis = app(TeknikServisBaskiSablonuServisi::class);
        $servis->firmaSablonlariniHazirla($firmaId, 'teslim_belgesi');

        return TeknikServisBaskiSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('sablon_turu', 'teslim_belgesi')
            ->where('aktif', true)
            ->orderByDesc('varsayilan_mi')
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array<string, string>
     */
    private function sablonVerileri(): array
    {
        $firma = $this->kayit?->firma;
        $musteriAd = (string) ($this->kayit->cari?->ad ?: $this->kayit->musteri_ad_soyad ?: '-');
        $musteriTel = (string) ($this->kayit->musteri_tel ?: $this->kayit->cari?->telefon ?: $this->kayit->cari?->gsm ?: '-');
        $musteriAdres = (string) ($this->kayit->cari?->adres ?: '-');
        $toplamlar = $this->toplamlar();

        return [
            '{{FIRMA_UNVAN}}' => e((string) ($firma?->ad ?: 'Yalova Kamera')),
            '{{FIRMA_TELEFON}}' => e((string) ($firma?->telefon ?: '-')),
            '{{FIRMA_EPOSTA}}' => e((string) ($firma?->eposta ?: '-')),
            '{{FIRMA_ADRES}}' => e((string) ($firma?->adres ?: '-')),
            '{{FIRMA_VERGI_NO}}' => e((string) ($firma?->vergi_no ?: '-')),
            '{{FIRMA_LOGO}}' => $this->firmaLogoHtml(),
            '{{SERVIS_NO}}' => e((string) ($this->kayit->fis_no ?: '-')),
            '{{KABUL_TARIHI}}' => e(optional($this->kayit->kabul_tarihi)->format('d.m.Y H:i') ?? '-'),
            '{{TESLIM_TARIHI}}' => e(optional($this->kayit->teslim_tarihi)->format('d.m.Y H:i') ?? now()->format('d.m.Y H:i')),
            '{{MUSTERI_AD}}' => e($musteriAd),
            '{{MUSTERI_TC_NO}}' => e((string) ($this->kayit->cari?->tc_no ?: '-')),
            '{{MUSTERI_TEL}}' => e($musteriTel),
            '{{MUSTERI_ADRES}}' => e($musteriAdres),
            '{{CIHAZ}}' => e((string) ($this->kayit->cihaz?->ad ?: '-')),
            '{{CIHAZ_TURU}}' => e((string) ($this->kayit->cihaz?->ad ?: '-')),
            '{{MARKA}}' => e((string) ($this->kayit->marka?->ad ?: '-')),
            '{{MODEL_NO}}' => e((string) ($this->kayit->model_no ?: '-')),
            '{{SERI_NO}}' => e((string) ($this->kayit->seri_no ?: '-')),
            '{{AKSESUARLAR}}' => e($this->aksesuarMetni()),
            '{{FIZIKSEL_DURUM}}' => nl2br(e((string) ($this->kayit->musteriye_gorunen_not ?: $this->kayit->ic_servis_notu ?: '-'))),
            '{{CIHAZ_FOTOGRAFLARI}}' => $this->cihazFotograflariHtml(),
            '{{SERVIS_DURUMU}}' => e((string) ($this->kayit->servisDurumu?->ad ?: '-')),
            '{{ARIZA_ACIKLAMASI}}' => nl2br(e((string) ($this->kayit->musteri_sikayeti ?: $this->kayit->musteriye_gorunen_not ?: '-'))),
            '{{TESLIM_NOTU}}' => nl2br(e((string) ($this->kayit->teslim_notu ?: '-'))),
            '{{TESPIT_UCRETI}}' => '',
            '{{TOPLAM_TUTAR}}' => e($this->paraFormatla($toplamlar['odenecek_tutar'])),
            '{{SEHIR}}' => e($this->firmaSehirMetni($firma)),
            '{{YAPILAN_ISLEMLER}}' => nl2br(e((string) ($this->kayit->yapilan_islemler ?: '-'))),
            '{{MUSTERI_ONAY_DURUMU}}' => e($this->musteriOnayDurumuMetni()),
            '{{ONAY_NOTU}}' => nl2br(e($this->onayNotuMetni())),
            '{{STOK_KALEMLERI_TABLOSU}}' => $this->stokKalemleriTablosuHtml(),
            '{{TOPLAM_OZETI}}' => $this->toplamOzetiHtml($toplamlar),
            '{{ODEME_OZETI}}' => $this->odemeOzetiHtml($toplamlar),
            '{{TAHSILAT_TABLOSU}}' => $this->tahsilatTablosuHtml(),
        ];
    }

    private function aksesuarMetni(): string
    {
        $adlar = $this->kayit?->aksesuarlar
            ?->map(fn ($aksesuar) => trim((string) ($aksesuar->ad ?? '')))
            ->filter()
            ->values()
            ->all();

        if (is_array($adlar) && $adlar !== []) {
            return implode(', ', $adlar);
        }

        return '-';
    }

    private function cihazFotograflariHtml(): string
    {
        $gorseller = collect((array) ($this->kayit?->cihaz_gorseller ?? []))
            ->filter(fn ($path) => is_string($path) && trim($path) !== '')
            ->take(4)
            ->values();

        if ($gorseller->isEmpty()) {
            return '';
        }

        $items = $gorseller->map(function (string $path): string {
            try {
                $url = Storage::disk('public')->url($path);
            } catch (\Throwable) {
                return '';
            }

            return '<div class="sk-photo-item"><img src="'.e($url).'" alt="Cihaz fotografi"></div>';
        })->filter()->implode('');

        if ($items === '') {
            return '';
        }

        return '<div class="sk-photo-gallery-wrap">'
            .'<div class="sk-photo-gallery-title">Cihaz Fotograflari</div>'
            .'<div class="sk-photo-gallery">'.$items.'</div>'
            .'</div>';
    }

    private function musteriOnayDurumuMetni(): string
    {
        $durum = $this->durumDegeri($this->kayit?->musteri_onay_durumu ?? '');

        return $durum !== '' ? mb_convert_case($durum, MB_CASE_TITLE, 'UTF-8') : 'Beklemede';
    }

    private function onayNotuMetni(): string
    {
        return trim((string) ($this->kayit?->onay_notu ?? '')) ?: 'Yazılı, SMS, telefon, WhatsApp veya e-posta onayı ile işlem ilerletilir.';
    }

    private function stokKalemleriTablosuHtml(): string
    {
        $kalemler = $this->kayit?->kalemler ?? collect();
        if ($kalemler->isEmpty()) {
            return '<div class="tsf-muted">Stok veya hizmet kalemi yok.</div>';
        }

        $satirlar = $kalemler->map(function ($kalem): string {
            $stokAdi = trim((string) ($kalem->stok?->ad ?? ''));
            $aciklama = trim((string) ($kalem->aciklama ?? ''));
            $ad = $stokAdi !== '' ? $stokAdi : ($aciklama !== '' ? $aciklama : '-');
            $detay = $stokAdi !== '' && $aciklama !== '' && $aciklama !== $stokAdi ? $aciklama : '-';
            $paraBirimi = strtoupper((string) ($kalem->para_birimi ?: 'TRY'));
            $hesap = $this->satirHesabi([
                'miktar' => $kalem->miktar,
                'birim_fiyat' => $kalem->birim_fiyat,
                'iskonto_tutari' => $kalem->iskonto_tutari,
                'kdv_orani' => $kalem->kdv_orani,
            ]);

            return '<tr>'
                .'<td>'.e($ad).'</td>'
                .'<td>'.e($detay).'</td>'
                .'<td class="is-right">'.e($this->miktarFormatla((float) $kalem->miktar)).'</td>'
                .'<td class="is-right">'.e($this->paraFormatla((float) $kalem->birim_fiyat, $paraBirimi)).'</td>'
                .'<td class="is-right">'.e($this->paraFormatla((float) ($kalem->iskonto_tutari ?? 0), $paraBirimi)).'</td>'
                .'<td class="is-right">'.e($this->paraFormatla($hesap['net'], $paraBirimi)).'</td>'
                .'</tr>';
        })->implode('');

        return '<table class="tsf-table">'
            .'<thead><tr><th>Kalem</th><th>Açıklama</th><th class="is-right">Miktar</th><th class="is-right">Birim Fiyat</th><th class="is-right">İskonto Tutarı</th><th class="is-right">Tutar</th></tr></thead>'
            .'<tbody>'.$satirlar.'</tbody>'
            .'</table>';
    }

    /**
     * @return array{mal_hizmet_toplam_tutari:float,toplam_iskonto:float,hesaplanan_kdv:float,vergiler_dahil_toplam_tutar:float,odenecek_tutar:float}
     */
    private function toplamlar(): array
    {
        $kalemler = $this->kayit?->kalemler
            ?->map(fn ($kalem): array => [
                'miktar' => $kalem->miktar,
                'birim_fiyat' => $kalem->birim_fiyat,
                'iskonto_tutari' => $kalem->iskonto_tutari,
                'kdv_orani' => $kalem->kdv_orani,
            ])
            ->values()
            ->all() ?? [];

        $toplamlar = TeknikServisKayitFormSchema::stokOzetHesaplaKalemDizisi($kalemler);
        if ((float) $toplamlar['odenecek_tutar'] <= 0 && (float) ($this->kayit?->toplam_tutar ?? 0) > 0) {
            $toplamlar['vergiler_dahil_toplam_tutar'] = (float) $this->kayit->toplam_tutar;
            $toplamlar['odenecek_tutar'] = (float) $this->kayit->toplam_tutar;
        }

        return $toplamlar;
    }

    /**
     * @param array{mal_hizmet_toplam_tutari:float,toplam_iskonto:float,hesaplanan_kdv:float,vergiler_dahil_toplam_tutar:float,odenecek_tutar:float} $toplamlar
     */
    private function toplamOzetiHtml(array $toplamlar): string
    {
        $kdvHaricToplam = $this->kdvHaricToplam($toplamlar);

        return '<div class="tsf-summary">'
            .$this->ozetSatiri('Mal/Hizmet Toplamı', $this->paraFormatla($toplamlar['mal_hizmet_toplam_tutari']))
            .$this->ozetSatiri('Toplam İskonto', $this->paraFormatla($toplamlar['toplam_iskonto']))
            .$this->ozetSatiri('Genel Toplam', $this->paraFormatla($kdvHaricToplam))
            .'</div>';
    }

    /**
     * @param array{mal_hizmet_toplam_tutari:float,toplam_iskonto:float,hesaplanan_kdv:float,vergiler_dahil_toplam_tutar:float,odenecek_tutar:float} $toplamlar
     */
    private function odemeOzetiHtml(array $toplamlar): string
    {
        $kdvHaricToplam = $this->kdvHaricToplam($toplamlar);
        $tahsilatDagilimi = collect($this->kayit?->tahsilatlar ?? [])
            ->where('durum', 'aktif')
            ->groupBy(fn ($tahsilat): string => strtoupper((string) ($tahsilat->kaynak_para_birimi ?: 'TRY')))
            ->map(fn ($satirlar): float => (float) $satirlar->sum('tutar'));
        $odenenEtiketi = $tahsilatDagilimi
            ->map(fn (float $tutar, $paraBirimi): string => $this->paraFormatla($tutar, (string) $paraBirimi))
            ->implode(' · ');
        if ($odenenEtiketi === '') {
            $odenenEtiketi = $this->paraFormatla((float) ($this->kayit?->odenen_tutar ?? 0));
        }
        $kalan = $tahsilatDagilimi->count() <= 1
            ? max($kdvHaricToplam - (float) ($tahsilatDagilimi->first() ?? $this->kayit?->odenen_tutar ?? 0), 0)
            : null;
        $durum = $this->durumDegeri($this->kayit?->odeme_durumu ?? '');

        return '<div class="tsf-summary">'
            .$this->ozetSatiri('Toplam Tutar', $this->paraFormatla($kdvHaricToplam))
            .$this->ozetSatiri('Ödenen', $odenenEtiketi)
            .$this->ozetSatiri('Kalan', $kalan === null ? 'Çoklu para birimi' : $this->paraFormatla($kalan))
            .$this->ozetSatiri('Ödeme Durumu', $durum !== '' ? $durum : '-')
            .'</div>';
    }

    /**
     * @param array{mal_hizmet_toplam_tutari:float,toplam_iskonto:float,hesaplanan_kdv:float,vergiler_dahil_toplam_tutar:float,odenecek_tutar:float} $toplamlar
     */
    private function kdvHaricToplam(array $toplamlar): float
    {
        return max(
            (float) $toplamlar['mal_hizmet_toplam_tutari'] - (float) $toplamlar['toplam_iskonto'],
            0
        );
    }

    private function tahsilatTablosuHtml(): string
    {
        $tahsilatlar = $this->kayit?->tahsilatlar ?? collect();
        if ($tahsilatlar->isEmpty()) {
            return '<div class="tsf-muted">Tahsilat kaydı yok.</div>';
        }

        $satirlar = $tahsilatlar->map(function ($tahsilat): string {
            return '<tr>'
                .'<td>'.e(optional($tahsilat->tarih)->format('d.m.Y H:i') ?? '-').'</td>'
                .'<td>'.e($this->kanalMetni((string) ($tahsilat->kanal ?? ''))).'</td>'
                .'<td>'.e((string) ($tahsilat->aciklama ?: '-')).'</td>'
                .'<td class="is-right">'.e($this->paraFormatla((float) $tahsilat->tutar, (string) ($tahsilat->kaynak_para_birimi ?: 'TRY'))).'</td>'
                .'<td>'.e($this->durumDegeri($tahsilat->durum ?? '') ?: '-').'</td>'
                .'</tr>';
        })->implode('');

        return '<table class="tsf-table">'
            .'<thead><tr><th>Tarih</th><th>Kanal</th><th>Açıklama</th><th class="is-right">Tutar</th><th>Durum</th></tr></thead>'
            .'<tbody>'.$satirlar.'</tbody>'
            .'</table>';
    }

    private function ozetSatiri(string $etiket, string $deger): string
    {
        return '<div class="tsf-summary-row"><span>'.e($etiket).'</span><strong>'.e($deger).'</strong></div>';
    }

    /**
     * @param array<string, mixed> $kalem
     * @return array{brut:float,iskonto:float,net:float,kdv:float,toplam:float}
     */
    private function satirHesabi(array $kalem): array
    {
        $miktar = (float) ($kalem['miktar'] ?? 0);
        $birimFiyat = (float) ($kalem['birim_fiyat'] ?? 0);
        $brut = round($miktar * $birimFiyat, 2);
        $iskonto = max(0.0, min((float) ($kalem['iskonto_tutari'] ?? 0), $brut));
        $net = max($brut - $iskonto, 0);
        $kdv = round($net * ((float) ($kalem['kdv_orani'] ?? 0) / 100), 2);

        return [
            'brut' => $brut,
            'iskonto' => $iskonto,
            'net' => $net,
            'kdv' => $kdv,
            'toplam' => round($net + $kdv, 2),
        ];
    }

    private function paraFormatla(float $tutar, string $paraBirimi = 'TRY'): string
    {
        return number_format($tutar, 2, ',', '.').' '.strtoupper($paraBirimi ?: 'TRY');
    }

    private function miktarFormatla(float $deger): string
    {
        $metin = number_format($deger, 2, ',', '.');

        return rtrim(rtrim($metin, '0'), ',');
    }

    private function kanalMetni(string $kanal): string
    {
        return match ($kanal) {
            'kasa' => 'Kasa',
            'banka' => 'Banka',
            'pos' => 'POS',
            default => $kanal !== '' ? ucfirst($kanal) : '-',
        };
    }

    private function durumDegeri(mixed $deger): string
    {
        if (is_object($deger) && property_exists($deger, 'value')) {
            $deger = $deger->value;
        }

        return trim(str_replace('_', ' ', (string) $deger));
    }

    private function firmaSehirMetni(?Firma $firma): string
    {
        $adres = trim((string) ($firma?->adres ?? ''));
        if ($adres === '') {
            return 'Yalova';
        }

        $parcalar = preg_split('/[,\/-]+/u', $adres) ?: [];
        $parcalar = array_values(array_filter(array_map('trim', $parcalar)));

        return $parcalar !== [] ? (string) end($parcalar) : 'Yalova';
    }

    private function firmaLogoHtml(): string
    {
        $logoUrl = $this->sablonLogoUrl() ?: $this->firmaLogoUrl();

        return $logoUrl ? '<img src="'.e($logoUrl).'" alt="Sablon logosu">' : '';
    }

    private function sablonLogoUrl(): ?string
    {
        return $this->dosyaUrlHazirla((string) ($this->sablon?->sablon_logo ?? ''));
    }

    private function firmaLogoUrl(): ?string
    {
        $firmaId = (int) ($this->kayit?->firma_id ?: $this->aktifFirmaId() ?: 0);
        if ($firmaId < 1) {
            return null;
        }

        $logo = (string) (app(FirmaAyarDeposu::class)->oku($firmaId, 'logo', '') ?? '');

        return $this->dosyaUrlHazirla($logo) ?: $this->dosyaUrlHazirla(self::DEFAULT_LOGO_PATH);
    }

    private function dosyaUrlHazirla(?string $yol): ?string
    {
        $yol = trim((string) $yol);
        if ($yol === '') {
            return null;
        }

        if (str_starts_with($yol, 'http://') || str_starts_with($yol, 'https://')) {
            return $yol;
        }

        try {
            return Storage::disk('public')->url($yol);
        } catch (\Throwable) {
            return null;
        }
    }

    private function aktifFirmaId(): ?int
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        if ($firmaId > 0) {
            return $firmaId;
        }

        $varsayilanFirmaId = (int) Firma::query()->orderBy('id')->value('id');

        return $varsayilanFirmaId > 0 ? $varsayilanFirmaId : null;
    }
}
