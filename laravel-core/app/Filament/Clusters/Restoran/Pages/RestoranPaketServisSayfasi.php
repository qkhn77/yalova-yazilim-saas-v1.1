<?php

namespace App\Filament\Clusters\Restoran\Pages;

use App\Filament\Clusters\Restoran as RestoranCluster;
use App\Filament\Clusters\Restoran\Kaynaklar\RestoranFilamentErisimYardimcisi;
use App\Models\Personel\Personel;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Services\Restoran\RestoranPaketServisServisi;
use App\Services\TenantContextService;
use App\Support\Restoran\RestoranYetkiSablonlari;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class RestoranPaketServisSayfasi extends Page
{
    protected static ?string $cluster = RestoranCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Paket Servis';

    protected static ?string $title = 'Paket Servis';

    protected static ?string $slug = 'paket-servis';

    protected static string $view = 'filament.clusters.restoran.pages.restoran-paket-servis';

    /** @var array<int, int|string|null> */
    public array $kuryeSecimleri = [];

    /** @var array<int, string|null> */
    public array $tahminiTeslimatSecimleri = [];

    /** @var array<int, string|null> */
    public array $teslimatNotlari = [];

    public string $paketDurumFiltresi = 'aktif';

    public string $siparisTipiFiltresi = 'tum';

    private ?int $aktifFirmaIdCache = null;

    private ?bool $guncellemeYetkisiCache = null;

    /**
     * @var Collection<int, RestoranAdisyonu>|null
     */
    private ?Collection $siparislerCache = null;

    /**
     * @var array<string, int|float>|null
     */
    private ?array $durumOzetiCache = null;

    private ?string $siparislerCacheAnahtari = null;

    public static function canAccess(): bool
    {
        return RestoranFilamentErisimYardimcisi::herhangiBirRestoranErisimiVarMi([
            RestoranYetkiSablonlari::PAKET_SERVIS_GORUNTULE,
            RestoranYetkiSablonlari::PAKET_SERVIS_GUNCELLE,
        ]);
    }

    public function guncellemeYetkisiVarMi(): bool
    {
        return $this->guncellemeYetkisiCache ??= RestoranFilamentErisimYardimcisi::restoranYetkisiVarMi(RestoranYetkiSablonlari::PAKET_SERVIS_GUNCELLE);
    }

    /**
     * @return Collection<int, RestoranAdisyonu>
     */
    public function siparisler(): Collection
    {
        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            return new Collection();
        }

        $cacheAnahtari = $this->paketDurumFiltresi.'|'.$this->siparisTipiFiltresi;
        if ($this->siparislerCache !== null && $this->siparislerCacheAnahtari === $cacheAnahtari) {
            return $this->siparislerCache;
        }

        $this->siparislerCacheAnahtari = $cacheAnahtari;
        $this->durumOzetiCache = null;
        $this->siparislerCache = RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->select([
                'id',
                'firma_id',
                'kurye_personel_id',
                'adisyon_no',
                'durum',
                'siparis_tipi',
                'paket_durum',
                'teslimat_telefon',
                'tahmini_teslimat_at',
                'teslimat_notu',
                'teslimat_adresi',
                'genel_toplam',
                'acilis_at',
            ])
            ->with(['kurye:id,ad_soyad'])
            ->where('firma_id', $firmaId)
            ->whereIn('siparis_tipi', ['paket', 'online'])
            ->when($this->siparisTipiFiltresi !== 'tum', fn ($query) => $query->where('siparis_tipi', $this->siparisTipiFiltresi))
            ->when(
                $this->paketDurumFiltresi === 'aktif',
                fn ($query) => $query->whereNotIn('paket_durum', [
                    RestoranAdisyonu::PAKET_DURUM_TESLIM_EDILDI,
                    RestoranAdisyonu::PAKET_DURUM_IPTAL,
                ]),
                fn ($query) => $query->where('paket_durum', $this->paketDurumFiltresi)
            )
            ->whereIn('durum', [
                RestoranAdisyonu::DURUM_ACIK,
                RestoranAdisyonu::DURUM_ODEMEDE,
                RestoranAdisyonu::DURUM_KAPANDI,
            ])
            ->orderBy('acilis_at')
            ->limit(100)
            ->get();

        return $this->siparislerCache;
    }

    /**
     * @return array<string, int>
     */
    public function durumOzeti(): array
    {
        if ($this->durumOzetiCache !== null) {
            return $this->durumOzetiCache;
        }

        $ozet = [
            'hazirlaniyor' => 0,
            'kuryede' => 0,
            'yolda' => 0,
            'geciken' => 0,
            'toplam' => 0,
            'tutar' => 0,
        ];

        foreach ($this->siparisler() as $siparis) {
            $ozet['toplam']++;
            $ozet['tutar'] += (float) $siparis->genel_toplam;

            if ($siparis->paket_durum === RestoranAdisyonu::PAKET_DURUM_HAZIRLANIYOR) {
                $ozet['hazirlaniyor']++;
            }

            if ($siparis->paket_durum === RestoranAdisyonu::PAKET_DURUM_KURYEE_ATANDI) {
                $ozet['kuryede']++;
            }

            if ($siparis->paket_durum === RestoranAdisyonu::PAKET_DURUM_YOLDA) {
                $ozet['yolda']++;
            }

            if ($siparis->tahmini_teslimat_at && Carbon::parse($siparis->tahmini_teslimat_at)->isPast()) {
                $ozet['geciken']++;
            }
        }

        $ozet['tutar'] = round((float) $ozet['tutar'], 2);

        return $this->durumOzetiCache = $ozet;
    }

    /**
     * @return array<int, string>
     */
    public function kuryeSecenekleri(): array
    {
        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            return [];
        }

        return Cache::remember(
            'restoran:paket-servis:kurye-secenekleri:v1:'.$firmaId,
            now()->addMinutes(5),
            fn (): array => Personel::query()
                ->where('firma_id', $firmaId)
                ->where('durum', Personel::DURUM_AKTIF)
                ->orderBy('ad_soyad')
                ->pluck('ad_soyad', 'id')
                ->all()
        );
    }

    public function kuryeAta(int $adisyonId): void
    {
        $this->yetkiKontrol();
        $kuryeId = (int) ($this->kuryeSecimleri[$adisyonId] ?? 0);
        abort_if($kuryeId <= 0, 422);

        app(RestoranPaketServisServisi::class)->kuryeAta($this->adisyonBul($adisyonId), $kuryeId);
        $this->bildir('Kurye atandı.');
    }

    public function teslimatPlanla(int $adisyonId): void
    {
        $this->yetkiKontrol();
        $tarih = $this->tahminiTeslimatSecimleri[$adisyonId] ?? null;
        abort_if(! $tarih, 422);

        app(RestoranPaketServisServisi::class)->teslimatPlanla(
            $this->adisyonBul($adisyonId),
            (string) $tarih,
            $this->teslimatNotlari[$adisyonId] ?? null,
        );

        $this->bildir('Teslimat plani guncellendi.');
    }

    public function yolaCikar(int $adisyonId): void
    {
        $this->yetkiKontrol();
        app(RestoranPaketServisServisi::class)->yolaCikar($this->adisyonBul($adisyonId));
        $this->bildir('Sipariş yola çıkarıldı.');
    }

    public function teslimEdildi(int $adisyonId): void
    {
        $this->yetkiKontrol();
        app(RestoranPaketServisServisi::class)->teslimEdildi($this->adisyonBul($adisyonId));
        $this->bildir('Sipariş teslim edildi.');
    }

    private function adisyonBul(int $adisyonId): RestoranAdisyonu
    {
        $firmaId = $this->aktifFirmaId();

        return RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereKey($adisyonId)
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
