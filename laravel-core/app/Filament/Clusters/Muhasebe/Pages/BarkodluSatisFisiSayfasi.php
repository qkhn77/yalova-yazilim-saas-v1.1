<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\BarkodluSatis\Guvenlik\BarkodluSatisFilamentErisimYardimcisi;
use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Models\Muhasebe\BarkodluSatis;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\SatisFisSablonu;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Servisler\BarkodluSatisAlacakOzetServisi;
use App\Muhasebe\Servisler\SatisFisSablonuServisi;
use App\Services\FirmaAyarDeposu;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Storage;

class BarkodluSatisFisiSayfasi extends Page
{
    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Satis fisi';

    protected static ?string $slug = 'satis/barkodlu-satis-fisi';

    protected static string $view = 'filament.clusters.muhasebe.pages.barkodlu-satis-fisi-sayfasi';

    public ?BarkodluSatis $satis = null;

    public ?FinansHareketi $tahsilat = null;

    public ?string $firmaLogoUrl = null;

    public ?string $firmaUnvan = null;

    public ?string $firmaTelefon = null;

    public ?string $firmaEposta = null;

    public ?string $firmaAdres = null;

    public bool $otomatikYazdir = false;

    public ?SatisFisSablonu $sablon = null;

    public ?string $sablonLogoUrl = null;

    public string $renderedHtml = '';

    public string $renderedCss = '';

    public string $printPageSize = '80mm auto';

    public string $printRightGap = '0mm';

    /** @var array<string,mixed> */
    public array $alacakOzeti = [];

    public bool $sablonFinansTokeniKullaniyor = false;

    public function mount(): void
    {
        $this->otomatikYazdir = (bool) request()->boolean('auto_print', false);
        $satisId = (int) request()->query('satis');
        if ($satisId > 0) {
            $this->satis = BarkodluSatis::query()
                ->with(['firma', 'cari', 'kalemler', 'olusturan'])
                ->whereKey($satisId)
                ->first();
        }

        if (! $this->satis) {
            return;
        }

        $this->alacakOzeti = app(BarkodluSatisAlacakOzetServisi::class)->ozet($this->satis->fresh(['cari']) ?? $this->satis);

        $this->tahsilat = FinansHareketi::query()
            ->where('firma_id', (int) $this->satis->firma_id)
            ->where('referans_turu', 'barkodlu_satis')
            ->where('referans_id', (int) $this->satis->id)
            ->where('durum', FinansHareketDurumu::Aktif->value)
            ->latest('id')
            ->first();

        $depo = app(FirmaAyarDeposu::class);
        $logo = (string) ($depo->oku((int) $this->satis->firma_id, 'logo', '') ?? '');
        $this->firmaLogoUrl = $this->logoUrlHazirla($logo);
        $this->firmaUnvan = (string) ($this->satis->firma?->ad ?? '');
        $this->firmaTelefon = (string) ($this->satis->firma?->telefon ?? '');
        $this->firmaEposta = (string) ($this->satis->firma?->eposta ?? '');
        $this->firmaAdres = (string) ($this->satis->firma?->adres ?? '');

        $sablonId = (int) request()->integer('sablon', 0);
        $this->sablon = app(SatisFisSablonuServisi::class)->seciliSablonGetir(
            (int) $this->satis->firma_id,
            $sablonId > 0 ? $sablonId : null
        );

        if ($this->sablon) {
            $this->sablonLogoUrl = $this->logoUrlHazirla((string) ($this->sablon->sablon_logo ?? ''));
            $this->renderedCss = (string) ($this->sablon->sablon_css ?? '');
            $this->renderedHtml = $this->sablonMetniniDoldur($this->sablon->sablon_html);
            $this->printPageSize = $this->sayfaTipindenPrintSize((string) $this->sablon->sayfa_tipi);
            $this->printRightGap = $this->sayfaTipindenSagBosluk((string) $this->sablon->sayfa_tipi);
        }
    }

    public function getHeading(): string|Htmlable
    {
        return 'Barkodlu satis fisi';
    }

    public static function canAccess(): bool
    {
        return BarkodluSatisFilamentErisimYardimcisi::herhangiBirBarkodluSatisYetkisiVarMi([
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_GORUNTULE,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_OLUSTUR,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_GUNCELLE,
        ]);
    }

    private function logoUrlHazirla(string $logo): ?string
    {
        $logo = trim($logo);
        if ($logo === '') {
            return null;
        }

        if (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://') || str_starts_with($logo, '/')) {
            return $logo;
        }

        return Storage::disk('public')->url($logo);
    }

    public function renderedHtmlCikti(): Htmlable
    {
        if ($this->renderedHtml !== '') {
            return new HtmlString($this->renderedHtml);
        }

        return new HtmlString('');
    }

    private function sablonMetniniDoldur(string $html): string
    {
        if (! $this->satis) {
            return $html;
        }

        $this->sablonFinansTokeniKullaniyor = str_contains($html, '{{ALACAK_PLAN_OZETI}}')
            || str_contains($html, '{{TAKSIT_OZETI}}')
            || str_contains($html, '{{TAHSILAT_TOPLAMI}}')
            || str_contains($html, '{{KALAN_TUTAR}}');

        $tokens = [
            '{{FIRMA_UNVAN}}' => e($this->firmaUnvan ?: 'Firma'),
            '{{FIRMA_TELEFON}}' => e($this->firmaTelefon ?: '-'),
            '{{FIRMA_EPOSTA}}' => e($this->firmaEposta ?: '-'),
            '{{FIRMA_ADRES}}' => e($this->firmaAdres ?: '-'),
            '{{FIRMA_LOGO}}' => ($this->sablonLogoUrl ?: $this->firmaLogoUrl)
                ? '<img src="'.e((string) ($this->sablonLogoUrl ?: $this->firmaLogoUrl)).'" alt="Firma logosu">'
                : '',
            '{{SATIS_NO}}' => e((string) ($this->satis->satis_no ?? '-')),
            '{{SATIS_TARIHI}}' => e((string) (optional($this->satis->satis_tarihi)->format('d.m.Y H:i') ?? '-')),
            '{{CARI_AD}}' => e((string) ($this->satis->cari?->ad ?? '-')),
            '{{KASIYER}}' => e((string) ($this->satis->olusturan?->name ?? '-')),
            '{{ODEME_TIPI}}' => e(strtoupper((string) ($this->satis->odeme_tipi ?? '-'))),
            '{{KALEMLER}}' => $this->kalemSatirlariHtml(),
            '{{ARA_TOPLAM}}' => e($this->parali((float) $this->satis->ara_toplam, (string) $this->satis->para_birimi)),
            '{{ISKONTO_TOPLAMI}}' => e($this->parali((float) $this->satis->iskonto_toplami, (string) $this->satis->para_birimi)),
            '{{KDV_TOPLAMI}}' => e($this->parali((float) $this->satis->kdv_toplami, (string) $this->satis->para_birimi)),
            '{{GENEL_TOPLAM}}' => e($this->parali((float) $this->satis->genel_toplam, (string) $this->satis->para_birimi)),
            '{{TAHSILAT_TOPLAMI}}' => e($this->parali((float) ($this->alacakOzeti['tahsilat_toplami'] ?? 0), (string) $this->satis->para_birimi)),
            '{{KALAN_TUTAR}}' => e($this->parali((float) ($this->alacakOzeti['finansal_acik_tutar'] ?? 0), (string) $this->satis->para_birimi)),
            '{{ALACAK_PLAN_OZETI}}' => $this->alacakPlanOzetiHtml(),
            '{{TAKSIT_OZETI}}' => $this->taksitOzetiHtml(),
            '{{SATIS_NOTU}}' => e((string) ($this->satis->not ?: '')),
        ];

        return strtr($html, $tokens);
    }

    public function ekFinansOzetiCikti(): Htmlable
    {
        if ($this->sablonFinansTokeniKullaniyor) {
            return new HtmlString('');
        }

        return new HtmlString($this->alacakPlanOzetiHtml());
    }

    private function kalemSatirlariHtml(): string
    {
        if (! $this->satis) {
            return '';
        }

        $satirlar = [];
        foreach ($this->satis->kalemler as $kalem) {
            $urun = e((string) $kalem->stok_adi);
            $seriler = array_values(array_filter(array_map(
                static fn ($seri): string => trim((string) $seri),
                (array) ($kalem->seri_nolari ?? [])
            ), static fn (string $seri): bool => $seri !== ''));
            if ($seriler !== []) {
                $urun .= '<div style="font-size:10px;color:#666;">Seri No Barkodu: '.e(implode(', ', $seriler)).'</div>';
            }

            $satirlar[] = '<tr>'
                .'<td>'.$urun.'</td>'
                .'<td>'.e((string) ($kalem->barkod ?: '-')).'</td>'
                .'<td>'.e(number_format((float) $kalem->miktar, 2, ',', '.')).'</td>'
                .'<td>'.e(number_format((float) $kalem->birim_fiyat, 2, ',', '.')).'</td>'
                .'<td>'.e(number_format((float) $kalem->satir_toplami, 2, ',', '.')).'</td>'
                .'</tr>';
        }

        return implode('', $satirlar);
    }

    private function parali(float $tutar, string $paraBirimi): string
    {
        return number_format($tutar, 2, ',', '.').' '.strtoupper($paraBirimi);
    }

    private function alacakPlanOzetiHtml(): string
    {
        if (! $this->satis || $this->alacakOzeti === []) {
            return '';
        }

        $plan = $this->alacakOzeti['plan'] ?? null;
        $finansalAcik = (float) ($this->alacakOzeti['finansal_acik_tutar'] ?? 0);
        if (! $plan && $finansalAcik <= 0.009) {
            return '';
        }

        $paraBirimi = (string) $this->satis->para_birimi;
        $satirlar = [
            '<div class="fis-finans-ozet">',
            '<div><span>Tahsilat</span><strong>'.e($this->parali((float) ($this->alacakOzeti['tahsilat_toplami'] ?? 0), $paraBirimi)).'</strong></div>',
            '<div><span>Kalan</span><strong>'.e($this->parali($finansalAcik, $paraBirimi)).'</strong></div>',
        ];

        if ($plan) {
            $satirlar[] = '<div><span>Plan</span><strong>#'.(int) $plan->id.' '.e(ucfirst(str_replace('_', ' ', (string) $plan->durum))).'</strong></div>';
            $satirlar[] = '<div><span>Son vade</span><strong>'.e((string) (optional($plan->son_vade_tarihi)->format('d.m.Y') ?? '-')).'</strong></div>';
        }

        $taksitOzeti = $this->taksitOzetiHtml();
        if ($taksitOzeti !== '') {
            $satirlar[] = $taksitOzeti;
        }

        $satirlar[] = '</div>';

        return implode('', $satirlar);
    }

    private function taksitOzetiHtml(): string
    {
        $taksitler = collect($this->alacakOzeti['taksitler'] ?? []);
        if ($taksitler->isEmpty()) {
            return '';
        }

        $paraBirimi = (string) ($this->satis?->para_birimi ?? 'TRY');
        $satirlar = ['<div class="fis-taksit-ozet">'];
        foreach ($taksitler as $taksit) {
            $satirlar[] = '<div><span>'
                .'#'.(int) $taksit->sira_no.' '.e((string) (optional($taksit->vade_tarihi)->format('d.m.Y') ?? '-'))
                .'</span><strong>'.e($this->parali((float) $taksit->kalan_tutar, $paraBirimi)).'</strong></div>';
        }
        $satirlar[] = '</div>';

        return implode('', $satirlar);
    }

    private function sayfaTipindenPrintSize(string $tip): string
    {
        return match ($tip) {
            'a4' => 'A4',
            '58mm' => '58mm auto',
            default => '80mm auto',
        };
    }

    private function sayfaTipindenSagBosluk(string $tip): string
    {
        return match ($tip) {
            '58mm' => '2mm',
            '80mm' => '3mm',
            default => '0mm',
        };
    }
}
