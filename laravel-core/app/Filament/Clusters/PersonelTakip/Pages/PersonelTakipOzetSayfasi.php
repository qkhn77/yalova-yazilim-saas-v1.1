<?php

namespace App\Filament\Clusters\PersonelTakip\Pages;

use App\Filament\Clusters\PersonelTakip as PersonelTakipCluster;
use App\Filament\Clusters\PersonelTakip\Kaynaklar\PersonelTakipFilamentErisimYardimcisi;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelAvansi;
use App\Models\Personel\PersonelBelgesi;
use App\Models\Personel\PersonelGirisCikisi;
use App\Models\Personel\PersonelIzni;
use App\Models\Personel\PersonelMaasDonemi;
use App\Models\Personel\PersonelVardiyasi;
use App\Services\TenantContextService;
use App\Support\PersonelTakip\PersonelTakipYetkiSablonlari;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class PersonelTakipOzetSayfasi extends Page
{
    protected static ?string $cluster = PersonelTakipCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $title = 'Personel Takip Özeti';

    protected static ?string $navigationLabel = 'Özet';

    protected static ?string $slug = 'ozet';

    protected static string $view = 'filament.clusters.personel-takip.pages.personel-takip-ozet';

    public function getHeading(): string|Htmlable
    {
        return 'Personel takip özeti';
    }

    public function getSubheading(): ?string
    {
        return 'Günlük operasyon, bekleyen onaylar ve maaş süreci için hızlı görünüm.';
    }

    public static function canAccess(): bool
    {
        return PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(PersonelTakipYetkiSablonlari::GORUNTULE)
            || PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(PersonelTakipYetkiSablonlari::RAPOR_GORUNTULE);
    }

    /**
     * @return array<string, int|float>
     */
    public function kpi(): array
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if (! $firmaId) {
            return [
                'aktif_personel' => 0,
                'bugun_vardiya' => 0,
                'acik_giris' => 0,
                'bekleyen_izin' => 0,
                'bekleyen_avans' => 0,
                'acik_avans_tutari' => 0.0,
                'acik_maas_donemi' => 0,
                'yenilenecek_belge' => 0,
            ];
        }

        $bugun = now()->toDateString();

        return [
            'aktif_personel' => Personel::query()
                ->where('firma_id', $firmaId)
                ->where('durum', Personel::DURUM_AKTIF)
                ->count(),
            'bugun_vardiya' => PersonelVardiyasi::query()
                ->where('firma_id', $firmaId)
                ->whereDate('tarih', $bugun)
                ->where('durum', '!=', 'iptal')
                ->count(),
            'acik_giris' => PersonelGirisCikisi::query()
                ->where('firma_id', $firmaId)
                ->whereNotNull('giris_at')
                ->whereNull('cikis_at')
                ->count(),
            'bekleyen_izin' => PersonelIzni::query()
                ->where('firma_id', $firmaId)
                ->where(function ($query): void {
                    $query->where('durum', 'onay_bekliyor')
                        ->orWhere('onay_durumu', 'onay_bekliyor');
                })
                ->count(),
            'bekleyen_avans' => PersonelAvansi::query()
                ->where('firma_id', $firmaId)
                ->where(function ($query): void {
                    $query->where('durum', 'bekliyor')
                        ->orWhere('onay_durumu', 'bekliyor');
                })
                ->count(),
            'acik_avans_tutari' => round((float) PersonelAvansi::query()
                ->where('firma_id', $firmaId)
                ->where('durum', 'onaylandi')
                ->where('maastan_dusuldu_mu', false)
                ->sum('kalan_tutar'), 2),
            'acik_maas_donemi' => PersonelMaasDonemi::query()
                ->where('firma_id', $firmaId)
                ->whereIn('durum', ['taslak', 'hesaplandi', 'onaylandi'])
                ->count(),
            'yenilenecek_belge' => PersonelBelgesi::query()
                ->where('firma_id', $firmaId)
                ->whereIn('durum', ['yenilenecek', 'suresi_doldu'])
                ->count(),
        ];
    }
}
