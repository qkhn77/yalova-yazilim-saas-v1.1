<?php

namespace App\Filament\Clusters\Restoran\Pages;

use App\Filament\Clusters\Restoran as RestoranCluster;
use App\Filament\Clusters\Restoran\Kaynaklar\RestoranFilamentErisimYardimcisi;
use App\Models\Restoran\RestoranAdisyonKalemi;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Services\Restoran\RestoranMutfakServisi;
use App\Services\TenantContextService;
use App\Support\Restoran\RestoranYetkiSablonlari;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class RestoranMutfakEkraniSayfasi extends Page
{
    protected static ?string $cluster = RestoranCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-fire';

    protected static ?string $navigationLabel = 'Mutfak';

    protected static ?string $title = 'Mutfak Ekranı';

    protected static ?string $slug = 'mutfak';

    protected static string $view = 'filament.clusters.restoran.pages.restoran-mutfak-ekrani';

    public string $durumFiltresi = 'aktif';

    public string $siparisTipiFiltresi = 'tum';

    private ?int $aktifFirmaIdCache = null;

    private ?bool $guncellemeYetkisiCache = null;

    /**
     * @var Collection<int, RestoranAdisyonKalemi>|null
     */
    private ?Collection $kalemlerCache = null;

    /**
     * @var array<string, int>|null
     */
    private ?array $durumOzetiCache = null;

    private ?string $kalemlerCacheAnahtari = null;

    public static function canAccess(): bool
    {
        return RestoranFilamentErisimYardimcisi::herhangiBirRestoranErisimiVarMi([
            RestoranYetkiSablonlari::MUTFAK_GORUNTULE,
            RestoranYetkiSablonlari::MUTFAK_GUNCELLE,
        ]);
    }

    public function guncellemeYetkisiVarMi(): bool
    {
        return $this->guncellemeYetkisiCache ??= RestoranFilamentErisimYardimcisi::restoranYetkisiVarMi(RestoranYetkiSablonlari::MUTFAK_GUNCELLE);
    }

    /**
     * @return Collection<int, RestoranAdisyonKalemi>
     */
    public function kalemler(): Collection
    {
        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            return new Collection();
        }

        $cacheAnahtari = $this->durumFiltresi.'|'.$this->siparisTipiFiltresi;
        if ($this->kalemlerCache !== null && $this->kalemlerCacheAnahtari === $cacheAnahtari) {
            return $this->kalemlerCache;
        }

        $this->kalemlerCacheAnahtari = $cacheAnahtari;
        $this->kalemlerCache = app(RestoranMutfakServisi::class)->mutfakKuyrugu(
            (int) $firmaId,
            $this->durumFiltresi,
            $this->siparisTipiFiltresi
        );

        return $this->kalemlerCache;
    }

    /**
     * @return array<string, int>
     */
    public function durumOzeti(): array
    {
        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            return [
                RestoranAdisyonKalemi::DURUM_YENI => 0,
                RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR => 0,
                RestoranAdisyonKalemi::DURUM_HAZIR => 0,
                'aktif_toplam' => 0,
                'geciken' => 0,
            ];
        }

        return $this->durumOzetiCache ??= app(RestoranMutfakServisi::class)->durumOzeti((int) $firmaId);
    }

    /**
     * @return array<string, Collection<int, RestoranAdisyonKalemi>>
     */
    public function kalemGruplari(): array
    {
        $gruplar = [
            RestoranAdisyonKalemi::DURUM_YENI => new Collection(),
            RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR => new Collection(),
            RestoranAdisyonKalemi::DURUM_HAZIR => new Collection(),
        ];

        foreach ($this->kalemler() as $kalem) {
            $durum = (string) $kalem->durum;

            if (array_key_exists($durum, $gruplar)) {
                $gruplar[$durum]->push($kalem);
            }
        }

        return $gruplar;
    }

    public function hazirlamayaAl(int $kalemId): void
    {
        $this->yetkiKontrol();
        app(RestoranMutfakServisi::class)->hazirlamayaAl($this->kalemBul($kalemId));
        $this->bildir('Kalem hazırlamaya alındı.');
    }

    public function hazirIsaretle(int $kalemId): void
    {
        $this->yetkiKontrol();
        app(RestoranMutfakServisi::class)->hazirIsaretle($this->kalemBul($kalemId));
        $this->bildir('Kalem hazır işaretlendi.');
    }

    public function servisEdildiIsaretle(int $kalemId): void
    {
        $this->yetkiKontrol();
        app(RestoranMutfakServisi::class)->servisEdildiIsaretle($this->kalemBul($kalemId));
        $this->bildir('Kalem servis edildi.');
    }

    private function kalemBul(int $kalemId): RestoranAdisyonKalemi
    {
        $firmaId = $this->aktifFirmaId();

        return RestoranAdisyonKalemi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereKey($kalemId)
            ->firstOrFail();
    }

    private function aktifFirmaId(): ?int
    {
        $firmaId = $this->aktifFirmaIdCache ??= app(TenantContextService::class)->aktifFirmaId();

        return $firmaId ? (int) $firmaId : null;
    }

    private function yetkiKontrol(): void
    {
        abort_unless($this->guncellemeYetkisiVarMi(), 403);
    }

    private function bildir(string $mesaj): void
    {
        Notification::make()
            ->title($mesaj)
            ->success()
            ->send();
    }
}
