<?php

namespace App\Filament\Clusters\TeknikServis\Resources\Concerns;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;

abstract class HizliTanimListesiSayfasi extends Page
{
    protected static string $view = 'filament.clusters.teknik-servis.resources.hizli-tanim-listesi';

    public bool $silmeModalAcik = false;

    public ?int $silmeKayitId = null;

    public ?int $silmeHedefId = null;

    /** @var array<int,string> */
    public array $silmeHedefleri = [];

    public int $silmeBagliKayitSayisi = 0;

    protected function getViewData(): array
    {
        return [
            'arama' => $this->aramaMetni(),
            'kayitlar' => $this->kayitlariGetir(),
            'resourceClass' => static::getResource(),
            'baslik' => static::getResource()::getPluralModelLabel(),
            'aktifFiltreVarMi' => $this->aktifFiltreVarMi(),
            'aktifFiltreSayisi' => $this->aktifFiltreSayisi(),
            'sayfaBoyutu' => $this->sayfaBoyutu(),
            'sayfaBoyutlari' => [10, 25, 50],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function silmeModaliniAc(int $id): void
    {
        $kayit = $this->getModel()::query()->findOrFail($id);

        $this->silmeKayitId = (int) $kayit->getKey();
        $this->silmeHedefId = null;
        $this->silmeHedefleri = $this->getModel()::query()
            ->whereKeyNot($id)
            ->where('firma_id', $kayit->firma_id)
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->mapWithKeys(fn ($ad, $kayitId): array => [(int) $kayitId => (string) $ad])
            ->all();
        $this->silmeBagliKayitSayisi = method_exists($kayit, 'teknikServisKayitlari')
            ? (int) $kayit->teknikServisKayitlari()->count()
            : 0;
        if (method_exists($kayit, 'arizalar')) {
            $this->silmeBagliKayitSayisi += (int) $kayit->arizalar()->count();
        }
        if (method_exists($kayit, 'teknikServisKayitCoklu')) {
            $this->silmeBagliKayitSayisi += (int) $kayit->teknikServisKayitCoklu()->count();
        }
        $this->silmeModalAcik = true;
    }

    public function silmeIptal(): void
    {
        $this->resetSilmeDurumu();
    }

    public function kayitSil(): void
    {
        if (! $this->silmeKayitId) {
            return;
        }

        $kayit = $this->getModel()::query()->findOrFail($this->silmeKayitId);
        $hedef = $this->silmeHedefId
            ? $this->getModel()::query()
                ->where('firma_id', $kayit->firma_id)
                ->find($this->silmeHedefId)
            : null;
        $kaynak = static::getResource();

        $silindi = method_exists($kaynak, 'silmeIslemi')
            ? $kaynak::silmeIslemi($kayit, $hedef)
            : (bool) $kayit->delete();

        if (! $silindi) {
            return;
        }

        $this->resetSilmeDurumu();

        Notification::make()
            ->title('Tanım silindi')
            ->success()
            ->send();
    }

    private function resetSilmeDurumu(): void
    {
        $this->silmeModalAcik = false;
        $this->silmeKayitId = null;
        $this->silmeHedefId = null;
        $this->silmeHedefleri = [];
        $this->silmeBagliKayitSayisi = 0;
    }

    private function kayitlariGetir(): Paginator
    {
        $model = $this->getModel();
        $tablo = (new $model)->getTable();

        $sorgu = $model::query()
            ->select([
                $tablo.'.id',
                $tablo.'.ad',
                $tablo.'.kod',
                $tablo.'.aktif',
                $tablo.'.siralama',
            ]);

        $this->aramaUygula($sorgu, $tablo);
        $this->filtreleriUygula($sorgu, $tablo);

        return $sorgu
            ->orderBy($tablo.'.siralama')
            ->orderBy($tablo.'.ad')
            ->simplePaginate($this->sayfaBoyutu())
            ->withQueryString();
    }

    private function aramaUygula(Builder $sorgu, string $tablo): void
    {
        $arama = $this->aramaMetni();
        if ($arama === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $arama).'%';

        $sorgu->where(function (Builder $query) use ($tablo, $like): void {
            $query
                ->where($tablo.'.ad', 'like', $like)
                ->orWhere($tablo.'.kod', 'like', $like);
        });
    }

    private function aramaMetni(): string
    {
        return trim((string) request()->query('q', ''));
    }

    private function filtreleriUygula(Builder $sorgu, string $tablo): void
    {
        $aktif = (string) request()->query('aktif', '');
        if ($aktif === '1' || $aktif === '0') {
            $sorgu->where($tablo.'.aktif', $aktif === '1');
        }
    }

    private function aktifFiltreVarMi(): bool
    {
        return $this->aramaMetni() !== ''
            || $this->aktifFiltreSayisi() > 0;
    }

    private function aktifFiltreSayisi(): int
    {
        $aktif = (string) request()->query('aktif', '');

        return ($aktif === '1' || $aktif === '0') ? 1 : 0;
    }

    private function sayfaBoyutu(): int
    {
        $adet = (int) request()->query('adet', 10);

        return in_array($adet, [10, 25, 50], true) ? $adet : 10;
    }
}
