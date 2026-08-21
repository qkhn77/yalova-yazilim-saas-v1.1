<?php

namespace App\Filament\Clusters\PersonelTakip\Pages;

use App\Filament\Clusters\PersonelTakip as PersonelTakipCluster;
use App\Filament\Clusters\PersonelTakip\Kaynaklar\PersonelTakipFilamentErisimYardimcisi;
use App\Models\Sube;
use App\Services\PersonelTakip\PersonelRaporCsvServisi;
use App\Services\PersonelTakip\PersonelRaporServisi;
use App\Services\TenantContextService;
use App\Support\PersonelTakip\PersonelTakipYetkiSablonlari;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PersonelRaporlariSayfasi extends Page
{
    protected static ?string $cluster = PersonelTakipCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $title = 'Personel Raporları';

    protected static ?string $navigationLabel = 'Raporlar';

    protected static ?string $slug = 'raporlar/personel-ozeti';

    protected static string $view = 'filament.clusters.personel-takip.pages.personel-raporlari';

    public ?string $baslangic_tarihi = null;

    public ?string $bitis_tarihi = null;

    public ?int $sube_id = null;

    public function mount(): void
    {
        $this->baslangic_tarihi ??= now()->startOfMonth()->toDateString();
        $this->bitis_tarihi ??= now()->toDateString();
    }

    public function getHeading(): string|Htmlable
    {
        return 'Personel raporları';
    }

    public function getSubheading(): ?string
    {
        return 'Vardiya, giriş-çıkış, izin, avans ve maaş maliyetleri.';
    }

    public static function canAccess(): bool
    {
        return PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(PersonelTakipYetkiSablonlari::RAPOR_GORUNTULE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rapor(): array
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if (! $firmaId) {
            return [
                'filtre' => [],
                'kpi' => [],
                'personel_performansi' => [],
            ];
        }

        return app(PersonelRaporServisi::class)->ozet(
            firmaId: $firmaId,
            baslangic: $this->baslangic_tarihi,
            bitis: $this->bitis_tarihi,
            subeId: $this->sube_id,
        );
    }

    public function csvIndir(): StreamedResponse
    {
        $icerik = app(PersonelRaporCsvServisi::class)->csvIcerigi($this->rapor());
        $dosyaAdi = sprintf(
            'personel-raporu-%s-%s.csv',
            $this->baslangic_tarihi ?: now()->startOfMonth()->toDateString(),
            $this->bitis_tarihi ?: now()->toDateString()
        );

        return response()->streamDownload(
            static function () use ($icerik): void {
                echo $icerik;
            },
            $dosyaAdi,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    /**
     * @return array<int, string>
     */
    public function subeSecenekleri(): array
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if (! $firmaId) {
            return [];
        }

        return Cache::remember(
            'personel:raporlar:sube-secenekleri:v1:'.$firmaId,
            now()->addMinutes(5),
            fn (): array => Sube::query()
                ->where('firma_id', $firmaId)
                ->orderBy('ad')
                ->pluck('ad', 'id')
                ->all()
        );
    }
}
