<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis;
use App\Models\Firma;
use App\Models\TeknikServis\TeknikServisBaskiSablonu;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Services\FirmaAyarDeposu;
use App\Services\TenantContextService;
use App\TeknikServis\Servisler\TeknikServisBaskiSablonuServisi;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Storage;

class KabulFisi extends Page
{
    private const DEFAULT_LOGO_PATH = 'teknik-servis-sablon-logolari/iV8V8hfdaQcYmGA4aF6QcjPweytbx7vZtweJFqso.png';

    protected static ?string $cluster = TeknikServis::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Kabul Fisi';

    protected static ?string $slug = 'ayarlar/kabul-fisi';

    protected static string $view = 'filament.clusters.teknik-servis.pages.kabul-fisi';

    public ?TeknikServisKaydi $kayit = null;

    public ?TeknikServisBaskiSablonu $sablon = null;

    public bool $otomatikYazdir = false;

    public function mount(): void
    {
        $this->otomatikYazdir = (bool) request()->boolean('auto_print', false);
        $kayitId = (int) request()->query('record', 0);

        if ($kayitId > 0) {
            $this->kayit = TeknikServisKaydi::query()
                ->with(['firma', 'cari', 'cihaz', 'marka', 'ariza', 'servisDurumu', 'kalemler', 'aksesuarlar'])
                ->find($kayitId);
        }

        $this->sablon = $this->varsayilanKabulFormuSablonu();
    }

    public function getHeading(): string|Htmlable
    {
        return 'Servis Kabul Formu';
    }

    public function icerik(): Htmlable
    {
        if (! $this->kayit) {
            return new HtmlString('<div class="text-sm text-gray-700">Servis kaydı bulunamadı.</div>');
        }

        $html = trim((string) ($this->sablon?->sablon_html ?? ''));
        if ($html === '') {
            return new HtmlString('<div class="text-sm text-gray-700">Servis kabul formu şablonu bulunamadı.</div>');
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
            default => '5mm',
        };

        return "@page { size: {$sayfaBoyutu}; margin: {$kenarBosluk}; }\n"
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

    private function varsayilanKabulFormuSablonu(): ?TeknikServisBaskiSablonu
    {
        $firmaId = (int) ($this->kayit?->firma_id ?: $this->aktifFirmaId() ?: 0);
        if ($firmaId < 1) {
            return null;
        }

        app(TeknikServisBaskiSablonuServisi::class)->firmaSablonlariniHazirla($firmaId, 'kabul_formu');

        return TeknikServisBaskiSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('sablon_turu', 'kabul_formu')
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
        $musteriTel = (string) ($this->kayit->musteri_tel ?: $this->kayit->cari?->telefon ?: '-');
        $musteriAdres = (string) ($this->kayit->cari?->adres ?: '-');

        return [
            '{{FIRMA_UNVAN}}' => e((string) ($firma?->ad ?: 'Yalova Bilgisayar Teknik Servis')),
            '{{FIRMA_TELEFON}}' => e((string) ($firma?->telefon ?: '0 (226) 352 07 24')),
            '{{FIRMA_EPOSTA}}' => e((string) ($firma?->eposta ?: 'info@yalovabilgisayar.com')),
            '{{FIRMA_ADRES}}' => e((string) ($firma?->adres ?: 'Sahil Mah. Yalı Cad. No:3/A Çiftlikköy/Yalova')),
            '{{FIRMA_VERGI_NO}}' => e((string) ($firma?->vergi_no ?: '45199618384')),
            '{{FIRMA_LOGO}}' => $this->firmaLogoHtml(),
            '{{SERVIS_NO}}' => e((string) ($this->kayit->fis_no ?: '-')),
            '{{KABUL_TARIHI}}' => e(optional($this->kayit->kabul_tarihi)->format('d.m.Y H:i') ?? now()->format('d.m.Y H:i')),
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
            '{{ARIZA_ACIKLAMASI}}' => nl2br(e((string) ($this->kayit->musteri_sikayeti ?: $this->kayit->ariza?->ad ?: '-'))),
            '{{TESLIM_NOTU}}' => nl2br(e((string) ($this->kayit->teslim_notu ?: '-'))),
            '{{TESPIT_UCRETI}}' => e('750,00 TL'),
            '{{TOPLAM_TUTAR}}' => e(number_format((float) ($this->kayit->toplam_tutar ?? 0), 2, ',', '.').' '.strtoupper((string) ($this->kayit->tahsilat_para_birimi ?: $this->kayit->cari?->para_birimi ?: 'TRY'))),
            '{{SEHIR}}' => e($this->firmaSehirMetni($firma)),
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

            return '<div class="ts-photo-item"><img src="'.e($url).'" alt="Cihaz fotoğrafı"></div>';
        })->filter()->implode('');

        if ($items === '') {
            return '';
        }

        return '<div class="ts-photo-gallery-wrap">'
            .'<div class="ts-label">Cihaz Fotoğrafları</div>'
            .'<div class="ts-photo-gallery">'.$items.'</div>'
            .'</div>';
    }

    private function firmaLogoHtml(): string
    {
        $logoUrl = $this->sablonLogoUrl() ?: $this->firmaLogoUrl();

        return $logoUrl ? '<img src="'.e($logoUrl).'" alt="Şablon logosu">' : '';
    }

    private function sablonLogoUrl(): ?string
    {
        return $this->dosyaUrlHazirla((string) ($this->sablon?->sablon_logo ?? ''));
    }

    private function firmaLogoUrl(): ?string
    {
        $firmaId = (int) ($this->kayit?->firma_id ?: $this->aktifFirmaId() ?: 0);
        if ($firmaId < 1) {
            return $this->dosyaUrlHazirla(self::DEFAULT_LOGO_PATH);
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
