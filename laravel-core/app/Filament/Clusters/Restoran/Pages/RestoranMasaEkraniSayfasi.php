<?php

namespace App\Filament\Clusters\Restoran\Pages;

use App\Filament\Clusters\Restoran as RestoranCluster;
use App\Filament\Clusters\Restoran\Kaynaklar\RestoranFilamentErisimYardimcisi;
use App\Filament\Clusters\Restoran\Resources\RestoranAdisyonKaynagi;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Restoran\RestoranMasasi;
use App\Models\Restoran\RestoranMenuUrunu;
use App\Models\Restoran\RestoranSalonu;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Models\Sube;
use App\Services\Restoran\RestoranMasaOperasyonServisi;
use App\Services\Restoran\RestoranSiparisKalemServisi;
use App\Services\TenantContextService;
use App\Support\Restoran\RestoranYetkiSablonlari;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class RestoranMasaEkraniSayfasi extends Page
{
    protected static ?string $cluster = RestoranCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Masa Ekrani';

    protected static ?string $title = 'Masa Ekrani';

    protected static ?string $slug = 'masa-ekrani';

    protected static string $view = 'filament.clusters.restoran.pages.restoran-masa-ekrani';

    public string $durumFiltresi = 'tum';

    public string $subeFiltresi = 'tum';

    public string $salonFiltresi = 'tum';

    public ?int $urunEkleAdisyonId = null;

    /** @var array{menu_urunu_id: int|null, miktar: float|int|string, mutfak_notu: string|null} */
    public array $siparisFormu = [
        'menu_urunu_id' => null,
        'miktar' => 1,
        'mutfak_notu' => null,
    ];

    private ?Collection $masalarMemo = null;

    private ?string $masalarMemoAnahtari = null;

    private ?int $aktifFirmaIdCache = null;

    private ?bool $guncellemeYetkisiCache = null;

    /** @var array<string, int>|null */
    private ?array $durumSayilariMemo = null;

    /** @var array<string, float|int>|null */
    private ?array $operasyonOzetiMemo = null;

    /** @var array<int|string, string>|null */
    private ?array $subeSecenekleriMemo = null;

    /** @var array<int|string, string>|null */
    private ?array $salonSecenekleriMemo = null;

    private ?string $salonSecenekleriMemoAnahtari = null;

    /** @var array<int, array<int, string>> */
    private array $menuUrunuSecenekleriMemo = [];

    public function mount(): void
    {
        $adisyonId = (int) request()->integer('urun_ekle', 0);

        if ($adisyonId < 1 || ! $this->guncellemeYetkisiVarMi()) {
            return;
        }

        try {
            $this->adisyonBul($adisyonId);
        } catch (Throwable) {
            return;
        }

        $this->urunEkleAdisyonId = $adisyonId;
    }

    public static function canAccess(): bool
    {
        return RestoranFilamentErisimYardimcisi::herhangiBirRestoranErisimiVarMi([
            RestoranYetkiSablonlari::MASA_GORUNTULE,
            RestoranYetkiSablonlari::ADISYON_GORUNTULE,
        ]);
    }

    public function guncellemeYetkisiVarMi(): bool
    {
        return $this->guncellemeYetkisiCache ??= RestoranFilamentErisimYardimcisi::restoranYetkisiVarMi(RestoranYetkiSablonlari::ADISYON_OLUSTUR)
            || RestoranFilamentErisimYardimcisi::restoranYetkisiVarMi(RestoranYetkiSablonlari::ADISYON_GUNCELLE);
    }

    /**
     * @return Collection<int, RestoranMasasi>
     */
    public function masalar(): Collection
    {
        $memoAnahtari = implode('|', [
            (int) ($this->aktifFirmaId() ?? 0),
            $this->subeFiltresi,
            $this->salonFiltresi,
            $this->durumFiltresi,
        ]);

        if ($this->masalarMemo instanceof Collection && $this->masalarMemoAnahtari === $memoAnahtari) {
            return $this->masalarMemo;
        }

        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            return new Collection();
        }

        $this->masalarMemoAnahtari = $memoAnahtari;

        return $this->masalarMemo = RestoranMasasi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->select([
                'id',
                'firma_id',
                'sube_id',
                'salon_id',
                'ad',
                'durum',
                'siralama',
            ])
            ->with([
                'salon:id,ad',
                'sube:id,ad',
                'adisyonlar' => function ($query) use ($firmaId): void {
                    $query
                        ->withoutGlobalScope(FirmaIdTenantScope::class)
                        ->select([
                            'id',
                            'firma_id',
                            'masa_id',
                            'adisyon_no',
                            'durum',
                            'genel_toplam',
                            'para_birimi',
                            'acilis_at',
                        ])
                        ->withCount('kalemler')
                        ->where('firma_id', $firmaId)
                        ->whereIn('durum', [RestoranAdisyonu::DURUM_ACIK, RestoranAdisyonu::DURUM_ODEMEDE])
                        ->orderByDesc('acilis_at');
                },
            ])
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->when($this->durumFiltresi !== 'tum', fn ($query) => $query->where('durum', $this->durumFiltresi))
            ->when($this->subeFiltresi !== 'tum', fn ($query) => $query->where('sube_id', (int) $this->subeFiltresi))
            ->when($this->salonFiltresi !== 'tum', fn ($query) => $query->where('salon_id', (int) $this->salonFiltresi))
            ->orderBy('salon_id')
            ->orderBy('siralama')
            ->orderBy('ad')
            ->limit(250)
            ->get();
    }

    /**
     * @return array<string, int>
     */
    public function durumSayilari(): array
    {
        if ($this->durumSayilariMemo !== null) {
            return $this->durumSayilariMemo;
        }

        $sayilar = [
            RestoranMasasi::DURUM_BOS => 0,
            RestoranMasasi::DURUM_DOLU => 0,
            RestoranMasasi::DURUM_REZERVE => 0,
            RestoranMasasi::DURUM_KAPALI => 0,
        ];

        foreach ($this->masalar() as $masa) {
            $sayilar[(string) $masa->durum] = ($sayilar[(string) $masa->durum] ?? 0) + 1;
        }

        return $this->durumSayilariMemo = $sayilar;
    }

    /**
     * @return array<string, float|int>
     */
    public function operasyonOzeti(): array
    {
        if ($this->operasyonOzetiMemo !== null) {
            return $this->operasyonOzetiMemo;
        }

        $masalar = $this->masalar();
        $toplamMasa = $masalar->count();
        $acikAdisyonSayisi = 0;
        $acikAdisyonToplami = 0.0;
        $acikKalemSayisi = 0;

        foreach ($masalar as $masa) {
            $adisyon = $masa->adisyonlar->first();
            if (! $adisyon) {
                continue;
            }

            $acikAdisyonSayisi++;
            $acikAdisyonToplami += (float) $adisyon->genel_toplam;
            $acikKalemSayisi += (int) $adisyon->kalemler_count;
        }

        return $this->operasyonOzetiMemo = [
            'toplam_masa' => $toplamMasa,
            'acik_adisyon_sayisi' => $acikAdisyonSayisi,
            'acik_kalem_sayisi' => $acikKalemSayisi,
            'acik_adisyon_toplami' => round($acikAdisyonToplami, 2),
            'doluluk_orani' => $toplamMasa > 0 ? round(($acikAdisyonSayisi / $toplamMasa) * 100, 2) : 0.0,
        ];
    }

    /**
     * @return array<int|string, string>
     */
    public function subeSecenekleri(): array
    {
        if ($this->subeSecenekleriMemo !== null) {
            return $this->subeSecenekleriMemo;
        }

        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            return ['tum' => 'Tüm şubeler'];
        }

        return $this->subeSecenekleriMemo = ['tum' => 'Tüm şubeler'] + Sube::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function salonSecenekleri(): array
    {
        $cacheAnahtari = (string) $this->subeFiltresi;
        if ($this->salonSecenekleriMemo !== null && $this->salonSecenekleriMemoAnahtari === $cacheAnahtari) {
            return $this->salonSecenekleriMemo;
        }

        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            return ['tum' => 'Tüm salonlar'];
        }

        $this->salonSecenekleriMemoAnahtari = $cacheAnahtari;

        return $this->salonSecenekleriMemo = ['tum' => 'Tüm salonlar'] + RestoranSalonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->when($this->subeFiltresi !== 'tum', fn ($query) => $query->where('sube_id', (int) $this->subeFiltresi))
            ->orderBy('siralama')
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->all();
    }

    public function masaAdisyonuAc(int $masaId): void
    {
        $this->yetkiKontrol(RestoranYetkiSablonlari::ADISYON_OLUSTUR);
        $adisyon = app(RestoranMasaOperasyonServisi::class)->masaAdisyonuAc($this->masaBul($masaId));

        $this->verileriYenile();
        $this->urunEkleFormunuAc((int) $adisyon->getKey());

        $this->bildir('Adisyon acildi.');
    }

    public function odemeyeAl(int $adisyonId): void
    {
        $this->yetkiKontrol(RestoranYetkiSablonlari::ADISYON_GUNCELLE);
        app(RestoranMasaOperasyonServisi::class)->odemeyeAl($this->adisyonBul($adisyonId));
        $this->verileriYenile();
        $this->bildir('Adisyon odemeye alindi.');
    }

    public function adisyonUrl(int $adisyonId): string
    {
        return RestoranAdisyonKaynagi::getUrl('edit', ['record' => $adisyonId]);
    }

    public function urunEkleUrl(int $adisyonId): string
    {
        return static::getUrl(['urun_ekle' => $adisyonId]);
    }

    public function urunEkleKapatUrl(): string
    {
        return static::getUrl();
    }

    public function urunEkleFormunuAc(int $adisyonId): void
    {
        $this->operasyonYetkisiKontrol();
        $this->adisyonBul($adisyonId);

        $this->urunEkleAdisyonId = $adisyonId;
        $this->siparisFormu = [
            'menu_urunu_id' => null,
            'miktar' => 1,
            'mutfak_notu' => null,
        ];
    }

    public function urunEkleFormunuKapat(): void
    {
        $this->urunEkleAdisyonId = null;
        $this->siparisFormu = [
            'menu_urunu_id' => null,
            'miktar' => 1,
            'mutfak_notu' => null,
        ];
    }

    public function siparisKalemiEkle(): void
    {
        $this->operasyonYetkisiKontrol();

        $adisyonId = (int) ($this->urunEkleAdisyonId ?? 0);
        $menuUrunuId = (int) ($this->siparisFormu['menu_urunu_id'] ?? 0);
        $miktar = (float) ($this->siparisFormu['miktar'] ?? 1);

        if ($adisyonId < 1 || $menuUrunuId < 1 || $miktar <= 0) {
            $this->bildir('Urun ve miktar secilmelidir.', 'danger');

            return;
        }

        $adisyon = $this->adisyonBul($adisyonId);
        $menuUrunu = RestoranMenuUrunu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $adisyon->firma_id)
            ->whereKey($menuUrunuId)
            ->firstOrFail();

        app(RestoranSiparisKalemServisi::class)->menuUrunuEkle(
            $adisyon,
            $menuUrunu,
            $miktar,
            $this->siparisFormu['mutfak_notu'] ?? null,
        );

        $this->verileriYenile();
        $this->urunEkleFormunuKapat();
        $this->bildir('Siparis kalemi eklendi.');
    }

    /**
     * @return array<int, string>
     */
    public function menuUrunuSecenekleri(int $adisyonId): array
    {
        if (array_key_exists($adisyonId, $this->menuUrunuSecenekleriMemo)) {
            return $this->menuUrunuSecenekleriMemo[$adisyonId];
        }

        $adisyon = $this->adisyonBul($adisyonId);

        return $this->menuUrunuSecenekleriMemo[$adisyonId] = RestoranMenuUrunu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->with('kategori')
            ->where('firma_id', $adisyon->firma_id)
            ->where('aktif_mi', true)
            ->where('stokta_var_mi', true)
            ->whereHas('kategori', function ($query) use ($adisyon): void {
                $query
                    ->withoutGlobalScope(FirmaIdTenantScope::class)
                    ->where('firma_id', $adisyon->firma_id)
                    ->where('aktif_mi', true)
                    ->when($adisyon->sube_id, function ($inner) use ($adisyon): void {
                        $inner->where(function ($subeQuery) use ($adisyon): void {
                            $subeQuery
                                ->whereNull('sube_id')
                                ->orWhere('sube_id', $adisyon->sube_id);
                        });
                    });
            })
            ->orderBy('ad')
            ->get()
            ->mapWithKeys(static fn (RestoranMenuUrunu $urun): array => [
                (int) $urun->id => trim(($urun->kategori?->ad ? $urun->kategori->ad.' - ' : '').$urun->ad),
            ])
            ->all();
    }

    private function masaBul(int $masaId): RestoranMasasi
    {
        $firmaId = $this->aktifFirmaId();

        return RestoranMasasi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereKey($masaId)
            ->firstOrFail();
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

    private function yetkiKontrol(string $yetki): void
    {
        abort_unless(RestoranFilamentErisimYardimcisi::restoranYetkisiVarMi($yetki), 403);
    }

    private function operasyonYetkisiKontrol(): void
    {
        abort_unless($this->guncellemeYetkisiVarMi(), 403);
    }

    private function verileriYenile(): void
    {
        $this->masalarMemo = null;
        $this->masalarMemoAnahtari = null;
        $this->durumSayilariMemo = null;
        $this->operasyonOzetiMemo = null;
        $this->menuUrunuSecenekleriMemo = [];
    }

    private function bildir(string $mesaj, string $durum = 'success'): void
    {
        $bildirim = Notification::make()->title($mesaj);

        match ($durum) {
            'danger' => $bildirim->danger(),
            'warning' => $bildirim->warning(),
            default => $bildirim->success(),
        };

        $bildirim->send();
    }
}
