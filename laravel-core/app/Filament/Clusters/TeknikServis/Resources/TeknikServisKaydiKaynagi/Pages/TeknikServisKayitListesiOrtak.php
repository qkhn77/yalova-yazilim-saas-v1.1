<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Concerns\TeknikServisKayitTabloTanimi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\TeknikServis\Enumlar\OdemeDurumu;
use App\TeknikServis\Enumlar\Oncelik;
use App\TeknikServis\Enumlar\ServisTipi;
use App\TeknikServis\Filament\TeknikServisListePreset;
use App\TeknikServis\Filament\TeknikServisListePresetleri;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Preset filtreli servis kayit listeleri icin hizli HTML liste.
 */
abstract class TeknikServisKayitListesiOrtak extends Page
{
    protected static string $resource = TeknikServisKaydiKaynagi::class;

    protected static string $view = 'filament.clusters.teknik-servis.resources.teknik-servis-kaydi-kaynagi.pages.hizli-kayit-listesi';

    /**
     * @var array<string, string>
     */
    private const SIRALANABILIR_KOLONLAR = [
        'fis' => 'teknik_servis_kayitlari.fis_no',
        'kabul' => 'teknik_servis_kayitlari.kabul_tarihi',
        'cari' => 'ts_cari.ad',
        'telefon' => 'teknik_servis_kayitlari.musteri_tel',
        'cihaz' => 'ts_cihaz.ad',
        'marka' => 'ts_marka.ad',
        'tip' => 'teknik_servis_kayitlari.servis_tipi',
        'durum' => 'ts_durum.ad',
        'oncelik' => 'teknik_servis_kayitlari.oncelik',
        'toplam' => 'teknik_servis_kayitlari.toplam_tutar',
        'teslim' => 'teknik_servis_kayitlari.teslim_tarihi',
    ];

    /**
     * @var array<int, int>
     */
    private const SAYFA_BOYUTLARI = [10, 25, 50];

    abstract protected static function listePreseti(): TeknikServisListePreset;

    protected function getViewData(): array
    {
        return [
            'arama' => $this->aramaMetni(),
            'kayitlar' => $this->kayitlariGetir(),
            'servisTipiFiltreleri' => $this->servisTipiFiltreleri(),
            'oncelikFiltreleri' => $this->oncelikFiltreleri(),
            'odemeFiltreleri' => $this->odemeFiltreleri(),
            'aktifFiltreVarMi' => $this->aktifFiltreVarMi(),
            'aktifFiltreSayisi' => $this->aktifFiltreSayisi(),
            'sayfaBoyutu' => $this->sayfaBoyutu(),
            'sayfaBoyutlari' => self::SAYFA_BOYUTLARI,
            'sirala' => $this->siralaAnahtari(),
            'yon' => $this->siralaYonu(),
        ];
    }

    protected function getHeaderActions(): array
    {
        $muhasebeKontrolUrl = $this->muhasebeKontrolUrl();

        if ($muhasebeKontrolUrl === null) {
            return [];
        }

        return [
            Actions\Action::make('muhasebeKontrolu')
                ->label('Muhasebe kontrolü')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('gray')
                ->url($muhasebeKontrolUrl),
        ];
    }

    private function muhasebeKontrolUrl(): ?string
    {
        try {
            return TeknikServisKaydiKaynagi::getUrl('muhasebe_kontrol');
        } catch (RouteNotFoundException) {
            return null;
        }
    }

    private function kayitlariGetir(): Paginator
    {
        $sorgu = TeknikServisKaydi::query();
        $sorgu = TeknikServisListePresetleri::uygula($sorgu, static::listePreseti());
        $sorgu = TeknikServisKayitTabloTanimi::listeSorgusunuOptimizeEt($sorgu);
        $this->aramaUygula($sorgu);
        $this->filtreleriUygula($sorgu);
        $this->siralamayiUygula($sorgu);

        return $sorgu
            ->simplePaginate($this->sayfaBoyutu())
            ->withQueryString();
    }

    private function aramaUygula(Builder $sorgu): void
    {
        $arama = $this->aramaMetni();
        if ($arama === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $arama).'%';

        $sorgu->where(function (Builder $query) use ($like): void {
            $query
                ->where('teknik_servis_kayitlari.fis_no', 'like', $like)
                ->orWhere('teknik_servis_kayitlari.musteri_tel', 'like', $like)
                ->orWhere('ts_cari.ad', 'like', $like)
                ->orWhere('ts_cihaz.ad', 'like', $like)
                ->orWhere('ts_marka.ad', 'like', $like);
        });
    }

    private function aramaMetni(): string
    {
        return trim((string) request()->query('q', ''));
    }

    private function filtreleriUygula(Builder $sorgu): void
    {
        $servisTipi = (string) request()->query('tip', '');
        if (array_key_exists($servisTipi, $this->servisTipiFiltreleri())) {
            $sorgu->where('teknik_servis_kayitlari.servis_tipi', $servisTipi);
        }

        $oncelik = (string) request()->query('oncelik', '');
        if (array_key_exists($oncelik, $this->oncelikFiltreleri())) {
            $sorgu->where('teknik_servis_kayitlari.oncelik', $oncelik);
        }

        $odeme = (string) request()->query('odeme', '');
        if (array_key_exists($odeme, $this->odemeFiltreleri())) {
            $sorgu->where('teknik_servis_kayitlari.odeme_durumu', $odeme);
        }

        foreach (['cari_id', 'cihaz_id', 'marka_id'] as $alan) {
            $deger = (int) request()->query($alan, 0);
            if ($deger > 0) {
                $sorgu->where('teknik_servis_kayitlari.'.$alan, $deger);
            }
        }

        foreach (['model_no', 'seri_no'] as $alan) {
            $deger = trim((string) request()->query($alan, ''));
            if ($deger !== '') {
                $sorgu->where('teknik_servis_kayitlari.'.$alan, $deger);
            }
        }

        $kayitliCihazId = (int) request()->query('kayitli_cihaz_id', 0);
        if ($kayitliCihazId > 0) {
            $sorgu->where('teknik_servis_kayitlari.kayitli_cihaz_id', $kayitliCihazId);
        }
    }

    private function siralamayiUygula(Builder $sorgu): void
    {
        $kolon = self::SIRALANABILIR_KOLONLAR[$this->siralaAnahtari()] ?? self::SIRALANABILIR_KOLONLAR['kabul'];
        $yon = $this->siralaYonu();

        $sorgu
            ->orderBy($kolon, $yon)
            ->orderBy('teknik_servis_kayitlari.id', $yon === 'asc' ? 'asc' : 'desc');
    }

    private function sayfaBoyutu(): int
    {
        $adet = (int) request()->query('adet', 10);

        return in_array($adet, self::SAYFA_BOYUTLARI, true) ? $adet : 10;
    }

    private function siralaAnahtari(): string
    {
        $sirala = (string) request()->query('sirala', 'kabul');

        return array_key_exists($sirala, self::SIRALANABILIR_KOLONLAR) ? $sirala : 'kabul';
    }

    private function siralaYonu(): string
    {
        return (string) request()->query('yon', 'desc') === 'asc' ? 'asc' : 'desc';
    }

    private function aktifFiltreVarMi(): bool
    {
        return $this->aramaMetni() !== ''
            || array_key_exists((string) request()->query('tip', ''), $this->servisTipiFiltreleri())
            || array_key_exists((string) request()->query('oncelik', ''), $this->oncelikFiltreleri())
            || array_key_exists((string) request()->query('odeme', ''), $this->odemeFiltreleri());
    }

    private function aktifFiltreSayisi(): int
    {
        $sayac = 0;

        if (array_key_exists((string) request()->query('tip', ''), $this->servisTipiFiltreleri())) {
            $sayac++;
        }

        if (array_key_exists((string) request()->query('oncelik', ''), $this->oncelikFiltreleri())) {
            $sayac++;
        }

        if (array_key_exists((string) request()->query('odeme', ''), $this->odemeFiltreleri())) {
            $sayac++;
        }

        return $sayac;
    }

    /**
     * @return array<string, string>
     */
    private function servisTipiFiltreleri(): array
    {
        return [
            ServisTipi::ArizaliCihaz->value => 'Arızalı cihaz',
            ServisTipi::DisServis->value => 'Dış servis',
            ServisTipi::Bakim->value => 'Bakım',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function oncelikFiltreleri(): array
    {
        return [
            Oncelik::Dusuk->value => 'Düşük',
            Oncelik::Normal->value => 'Normal',
            Oncelik::Acil->value => 'Acil',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function odemeFiltreleri(): array
    {
        return [
            OdemeDurumu::Odenmedi->value => 'Ödenmedi',
            OdemeDurumu::Kismi->value => 'Kısmi',
            OdemeDurumu::Odendi->value => 'Ödendi',
            OdemeDurumu::Iade->value => 'İade',
            OdemeDurumu::Iptal->value => 'İptal',
        ];
    }

    public function siralamaUrl(string $anahtar): string
    {
        $query = request()->query();
        unset($query['page']);

        $query['sirala'] = array_key_exists($anahtar, self::SIRALANABILIR_KOLONLAR) ? $anahtar : 'kabul';
        $query['yon'] = $this->siralaAnahtari() === $query['sirala'] && $this->siralaYonu() === 'asc' ? 'desc' : 'asc';

        return url()->current().'?'.http_build_query($query);
    }

    public function siralamaIkonu(string $anahtar): string
    {
        if ($this->siralaAnahtari() !== $anahtar) {
            return '↕';
        }

        return $this->siralaYonu() === 'asc' ? '↑' : '↓';
    }

    public function oncelikRengi(mixed $deger): string
    {
        $oncelik = $deger instanceof Oncelik ? $deger : Oncelik::tryFrom((string) $deger);

        return match ($oncelik) {
            Oncelik::Acil => 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/30',
            Oncelik::Dusuk => 'bg-gray-50 text-gray-600 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10',
            default => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30',
        };
    }

    public function odemeRengi(mixed $deger): string
    {
        $durum = $deger instanceof OdemeDurumu ? $deger : OdemeDurumu::tryFrom((string) $deger);

        return match ($durum) {
            OdemeDurumu::Odendi => 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-500/30',
            OdemeDurumu::Kismi => 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/30',
            OdemeDurumu::Iade, OdemeDurumu::Iptal => 'bg-gray-50 text-gray-600 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10',
            default => 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30',
        };
    }

    public function servisTipiEtiketi(mixed $deger): string
    {
        $tip = $deger instanceof ServisTipi ? $deger : ServisTipi::tryFrom((string) $deger);

        return match ($tip) {
            ServisTipi::ArizaliCihaz => 'Arızalı cihaz',
            ServisTipi::DisServis => 'Dış servis',
            ServisTipi::Bakim => 'Bakım',
            default => '-',
        };
    }

    public function oncelikEtiketi(mixed $deger): string
    {
        $oncelik = $deger instanceof Oncelik ? $deger : Oncelik::tryFrom((string) $deger);

        return match ($oncelik) {
            Oncelik::Dusuk => 'Düşük',
            Oncelik::Normal => 'Normal',
            Oncelik::Acil => 'Acil',
            default => '-',
        };
    }

    public function odemeEtiketi(mixed $deger): string
    {
        $durum = $deger instanceof OdemeDurumu ? $deger : OdemeDurumu::tryFrom((string) $deger);

        return match ($durum) {
            OdemeDurumu::Odenmedi => 'Ödenmedi',
            OdemeDurumu::Kismi => 'Kısmi',
            OdemeDurumu::Odendi => 'Ödendi',
            OdemeDurumu::Iade => 'İade',
            OdemeDurumu::Iptal => 'İptal',
            default => '-',
        };
    }

    public function paraFormatla(mixed $deger): string
    {
        return number_format((float) ($deger ?? 0), 2, ',', '.');
    }
}
