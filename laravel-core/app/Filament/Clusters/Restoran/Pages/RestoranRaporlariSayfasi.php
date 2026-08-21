<?php

namespace App\Filament\Clusters\Restoran\Pages;

use App\Filament\Clusters\Restoran as RestoranCluster;
use App\Filament\Clusters\Restoran\Kaynaklar\RestoranFilamentErisimYardimcisi;
use App\Services\Restoran\RestoranRaporServisi;
use App\Services\TenantContextService;
use App\Support\Restoran\RestoranYetkiSablonlari;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class RestoranRaporlariSayfasi extends Page
{
    protected static ?string $cluster = RestoranCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Raporlar';

    protected static ?string $title = 'Restoran Raporları';

    protected static ?string $slug = 'raporlar/genel';

    protected static string $view = 'filament.clusters.restoran.pages.restoran-raporlari';

    public string $baslangicTarihi;

    public string $bitisTarihi;

    private ?int $aktifFirmaIdCache = null;

    /** @var array<string, mixed>|null */
    private ?array $raporMemo = null;

    private ?string $raporMemoAnahtari = null;

    public function mount(): void
    {
        $this->baslangicTarihi = now()->toDateString();
        $this->bitisTarihi = now()->toDateString();
    }

    public static function canAccess(): bool
    {
        return RestoranFilamentErisimYardimcisi::restoranYetkisiVarMi(RestoranYetkiSablonlari::RAPOR_GORUNTULE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rapor(): array
    {
        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            return [
                'ozet' => [],
                'garsonlar' => collect(),
                'kasiyerler' => collect(),
                'kuryeler' => collect(),
                'mutfak' => collect(),
                'tahsilatlar' => collect(),
                'paket' => [],
                'urunler' => collect(),
                'karlilik' => [],
            ];
        }

        $baslangic = Carbon::parse($this->baslangicTarihi)->startOfDay();
        $bitis = Carbon::parse($this->bitisTarihi)->endOfDay();
        $cacheKey = 'restoran:raporlar:genel:v1:'.$firmaId.':'.$baslangic->toDateString().':'.$bitis->toDateString();

        if ($this->raporMemo !== null && $this->raporMemoAnahtari === $cacheKey) {
            return $this->raporMemo;
        }

        $servis = app(RestoranRaporServisi::class);

        $this->raporMemoAnahtari = $cacheKey;
        $this->raporMemo = Cache::remember($cacheKey, now()->addSeconds(30), fn (): array => [
            'ozet' => $servis->gunlukOzet((int) $firmaId, $baslangic),
            'garsonlar' => $servis->garsonPerformansi((int) $firmaId, $baslangic, $bitis),
            'kasiyerler' => $servis->kasiyerPerformansi((int) $firmaId, $baslangic, $bitis),
            'kuryeler' => $servis->kuryePerformansi((int) $firmaId, $baslangic, $bitis),
            'mutfak' => $servis->mutfakPerformansi((int) $firmaId, $baslangic, $bitis),
            'tahsilatlar' => $servis->tahsilatKanalOzeti((int) $firmaId, $baslangic, $bitis),
            'paket' => $servis->paketOperasyonOzeti((int) $firmaId, $baslangic, $bitis),
            'urunler' => $servis->urunSatisOzeti((int) $firmaId, $baslangic, $bitis),
            'karlilik' => $servis->stokKarlilikOzeti((int) $firmaId, $baslangic, $bitis),
        ]);

        return $this->raporMemo;
    }

    private function aktifFirmaId(): ?int
    {
        $firmaId = $this->aktifFirmaIdCache ??= app(TenantContextService::class)->aktifFirmaId();

        return $firmaId ? (int) $firmaId : null;
    }
}
