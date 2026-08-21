<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis as TeknikServisCluster;
use App\Filament\Clusters\TeknikServis\Kaynaklar\TeknikServisSayfaErisimleri;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKayitliCihaziKaynagi;
use App\Models\TeknikServis\TeknikServisKayitliCihazi;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;

class GarantiliCihazlarSayfasi extends Page
{
    use TeknikServisSayfaErisimleri;

    protected static ?string $cluster = TeknikServisCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $title = 'Garantili cihazlar';

    protected static ?string $navigationLabel = 'Garantili cihazlar';

    protected static ?string $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 52;

    protected static string $view = 'filament.clusters.teknik-servis.pages.garantili-cihazlar-sayfasi';

    protected static ?string $slug = 'operasyon/garantili-cihazlar';

    protected function getViewData(): array
    {
        return [
            'arama' => $this->aramaMetni(),
            'kayitlar' => $this->garantiKayitlariniGetir(),
            'aktifFiltreVarMi' => $this->aktifFiltreVarMi(),
            'aktifFiltreSayisi' => $this->aktifFiltreSayisi(),
            'sayfaBoyutu' => $this->sayfaBoyutu(),
            'sayfaBoyutlari' => [10, 25, 50],
        ];
    }

    private function garantiKayitlariniGetir(): Paginator
    {
        $sorgu = TeknikServisKayitliCihazi::query()
            ->select([
                'teknik_servis_kayitli_cihazlar.id',
                'teknik_servis_kayitli_cihazlar.model_no',
                'teknik_servis_kayitli_cihazlar.seri_no',
                'teknik_servis_kayitli_cihazlar.garanti_baslangic_tarihi',
                'teknik_servis_kayitli_cihazlar.garanti_bitis_tarihi',
                'cari.ad as cari_adi',
                'cari.telefon as cari_telefon',
                'cari.gsm as cari_gsm',
                'cihaz.ad as cihaz_adi',
                'marka.ad as marka_adi',
            ])
            ->leftJoin('cariler as cari', function ($join): void {
                $join->on('cari.id', '=', 'teknik_servis_kayitli_cihazlar.cari_id')->whereNull('cari.deleted_at');
            })
            ->leftJoin('teknik_servis_tanim_cihazlar as cihaz', function ($join): void {
                $join->on('cihaz.id', '=', 'teknik_servis_kayitli_cihazlar.cihaz_id')->whereNull('cihaz.deleted_at');
            })
            ->leftJoin('teknik_servis_tanim_markalar as marka', function ($join): void {
                $join->on('marka.id', '=', 'teknik_servis_kayitli_cihazlar.marka_id')->whereNull('marka.deleted_at');
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereNotNull('teknik_servis_kayitli_cihazlar.garanti_baslangic_tarihi')
                    ->orWhereNotNull('teknik_servis_kayitli_cihazlar.garanti_bitis_tarihi');
            });

        $this->aramaUygula($sorgu);
        $this->filtreleriUygula($sorgu);

        return $sorgu
            ->orderBy('teknik_servis_kayitli_cihazlar.garanti_bitis_tarihi')
            ->orderByDesc('teknik_servis_kayitli_cihazlar.id')
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
                ->where('teknik_servis_kayitli_cihazlar.seri_no', 'like', $like)
                ->orWhere('teknik_servis_kayitli_cihazlar.model_no', 'like', $like)
                ->orWhere('cari.ad', 'like', $like)
                ->orWhere('cihaz.ad', 'like', $like)
                ->orWhere('marka.ad', 'like', $like);
        });
    }

    private function filtreleriUygula(Builder $sorgu): void
    {
        $durum = (string) request()->query('durum', '');
        if ($durum === 'aktif') {
            $sorgu->whereDate('teknik_servis_kayitli_cihazlar.garanti_bitis_tarihi', '>=', now()->toDateString());
        } elseif ($durum === 'suresi-doldu') {
            $sorgu->whereDate('teknik_servis_kayitli_cihazlar.garanti_bitis_tarihi', '<', now()->toDateString());
        } elseif ($durum === 'tarih-yok') {
            $sorgu->whereNull('teknik_servis_kayitli_cihazlar.garanti_bitis_tarihi');
        }
    }

    private function aramaMetni(): string
    {
        return trim((string) request()->query('q', ''));
    }

    private function aktifFiltreVarMi(): bool
    {
        return $this->aramaMetni() !== ''
            || $this->aktifFiltreSayisi() > 0;
    }

    private function aktifFiltreSayisi(): int
    {
        return in_array((string) request()->query('durum', ''), ['aktif', 'suresi-doldu', 'tarih-yok'], true) ? 1 : 0;
    }

    private function sayfaBoyutu(): int
    {
        $adet = (int) request()->query('adet', 10);

        return in_array($adet, [10, 25, 50], true) ? $adet : 10;
    }

    public function telefonMetni(mixed $kayit): string
    {
        $telefon = trim((string) ($kayit->cari_telefon ?: $kayit->cari_gsm ?: $kayit->musteri_tel ?: ''));

        return $telefon !== '' ? $telefon : '-';
    }

    public function cihazMetni(mixed $kayit): string
    {
        $cihaz = trim((string) ($kayit->cihaz_adi ?? ''));
        $marka = trim((string) ($kayit->marka_adi ?? ''));
        $model = trim((string) ($kayit->model_no ?? ''));
        $metin = trim($cihaz.' '.$marka.' '.$model);

        return $metin !== '' ? $metin : '-';
    }

    public function garantiDurumu(mixed $kayit): string
    {
        if (! $kayit->garanti_bitis_tarihi) {
            return 'Tarih yok';
        }

        return Carbon::parse((string) $kayit->garanti_bitis_tarihi)->startOfDay()->lt(now()->startOfDay())
            ? 'Suresi doldu'
            : 'Aktif';
    }

    public function tarihMetni(mixed $tarih): string
    {
        return $tarih ? Carbon::parse((string) $tarih)->format('d.m.Y') : '-';
    }

    public function duzenleUrl(mixed $kayit): string
    {
        return TeknikServisKayitliCihaziKaynagi::getUrl('edit', ['record' => (int) $kayit->id]);
    }

    public function cihazNoMetni(mixed $kayit): string
    {
        return 'CIH-'.str_pad((string) $kayit->id, 6, '0', STR_PAD_LEFT);
    }
}
