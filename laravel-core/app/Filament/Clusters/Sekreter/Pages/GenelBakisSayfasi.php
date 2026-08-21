<?php

namespace App\Filament\Clusters\Sekreter\Pages;

use App\Filament\Clusters\Sekreter as SekreterCluster;
use App\Filament\Clusters\Sekreter\Resources\GorevKaynagi;
use App\Filament\Clusters\Sekreter\Resources\NotKaynagi;
use App\Filament\Clusters\Sekreter\Resources\RandevuKaynagi;
use App\Filament\Support\HasTenantVisibility;
use App\Models\SekreterGorevi;
use App\Models\SekreterRandevusu;
use App\Models\Muhasebe\AlacakPlanTaksiti;
use App\Models\Muhasebe\Cek;
use App\Models\Muhasebe\Senet;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Services\ModulErisimService;
use App\Services\TenantContextService;
use App\Support\SekreterYetkiSablonlari;
use Carbon\Carbon;
use Filament\Pages\Page;

class GenelBakisSayfasi extends Page
{
    use HasTenantVisibility;
    protected static ?string $cluster = SekreterCluster::class;
    protected static ?string $slug = 'genel-bakis';
    protected static ?string $title = 'Genel Bakış';
    protected static ?string $navigationLabel = 'Genel Bakış';
    protected static ?string $navigationGroup = 'Ajanda ve Görevler';
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.clusters.sekreter.pages.genel-bakis';
    protected static string $modulKodu = 'sekreter';
    protected static string $goruntuleYetkiKodu = SekreterYetkiSablonlari::GORUNTULE;

    /** Sekreter gezinmesi özel sidebar üzerinden yönetilir. */
    public function getSubNavigation(): array
    {
        return [];
    }

    protected function getViewData(): array
    {
        $bugun = today();
        $gorevler = SekreterGorevi::query()->whereDate('tarih', $bugun)->whereNotIn('durum', ['tamamlandi', 'iptal'])->orderBy('saat')->limit(10)->get();
        $yaklasan = SekreterGorevi::query()->whereDate('tarih', '>', $bugun)->whereNotIn('durum', ['tamamlandi', 'iptal'])->orderBy('tarih')->limit(10)->get();
        $randevular = SekreterRandevusu::query()->whereDate('baslangic_tarihi', $bugun)->orderBy('baslangic_saati')->limit(10)->get();
        $yaklasanRandevular = SekreterRandevusu::query()->whereDate('baslangic_tarihi', '>', $bugun)->orderBy('baslangic_tarihi')->orderBy('baslangic_saati')->limit(10)->get();
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        $modul = app(ModulErisimService::class);
        $muhasebeAktif = $firmaId && $modul->modulErisilebilirMi((int) $firmaId, 'muhasebe');
        $teknikServisAktif = $firmaId && $modul->modulErisilebilirMi((int) $firmaId, 'teknik_servis');
        $entegrasyonlar = [];

        if ($teknikServisAktif) {
            $servisler = TeknikServisKaydi::query()
                ->where(function ($query): void {
                    $query->whereBetween('garanti_bitis_tarihi', [today(), today()->addDays(30)])
                        ->orWhereBetween('bakim_tarihi', [today(), today()->addDays(30)]);
                })
                ->with('cari:id,ad')
                ->orderBy('garanti_bitis_tarihi')
                ->limit(10)
                ->get();
            foreach ($servisler as $servis) {
                $tarih = $servis->garanti_bitis_tarihi ?: $servis->bakim_tarihi;
                $entegrasyonlar[] = ['baslik' => 'Teknik Servis', 'metin' => ($servis->cari?->ad ?? 'Servis kaydı').' · '.($tarih?->format('d.m.Y') ?? '-')];
            }
        }

        if ($muhasebeAktif) {
            foreach (Cek::query()->whereBetween('vade_tarihi', [today(), today()->addDays(30)])->orderBy('vade_tarihi')->limit(5)->get() as $cek) {
                $entegrasyonlar[] = ['baslik' => 'Çek vadesi', 'metin' => ($cek->cek_no ?: 'Çek').' · '.$cek->vade_tarihi?->format('d.m.Y')];
            }
            foreach (Senet::query()->whereBetween('vade_tarihi', [today(), today()->addDays(30)])->orderBy('vade_tarihi')->limit(5)->get() as $senet) {
                $entegrasyonlar[] = ['baslik' => 'Senet vadesi', 'metin' => ($senet->senet_no ?: 'Senet').' · '.$senet->vade_tarihi?->format('d.m.Y')];
            }
            foreach (AlacakPlanTaksiti::query()->whereBetween('vade_tarihi', [today(), today()->addDays(30)])->where('kalan_tutar', '>', 0)->with('cari:id,ad')->orderBy('vade_tarihi')->limit(5)->get() as $taksit) {
                $entegrasyonlar[] = ['baslik' => 'Veresiye vadesi', 'metin' => ($taksit->cari?->ad ?? 'Cari').' · '.$taksit->vade_tarihi?->format('d.m.Y')];
            }
        }

        return [
            'kartlar' => [
                ['baslik' => 'Geciken görev', 'deger' => SekreterGorevi::query()->where('durum', 'bekliyor')->whereDate('tarih', '<', $bugun)->count(), 'renk' => 'danger'],
                ['baslik' => 'Bugünkü iş', 'deger' => $gorevler->count() + $randevular->count(), 'renk' => 'primary'],
                ['baslik' => 'Yaklaşan hatırlatma', 'deger' => SekreterGorevi::query()->whereBetween('tarih', [$bugun, $bugun->copy()->addDays(7)])->whereNotIn('durum', ['tamamlandi', 'iptal'])->where('hatirlatma_tipi', '!=', 'yok')->count() + SekreterRandevusu::query()->whereBetween('baslangic_tarihi', [$bugun, $bugun->copy()->addDays(7)])->where('hatirlatma_tipi', '!=', 'yok')->count(), 'renk' => 'warning'],
                ['baslik' => 'Tamamlanan görev', 'deger' => SekreterGorevi::query()->where('durum', 'tamamlandi')->whereDate('updated_at', $bugun)->count(), 'renk' => 'success'],
            ],
            'gorevler' => $gorevler,
            'randevular' => $randevular,
            'yaklasan' => $yaklasan,
            'yaklasanRandevular' => $yaklasanRandevular,
            'entegrasyonlar' => $entegrasyonlar,
            'gorevUrl' => GorevKaynagi::getUrl('create'),
            'randevuUrl' => RandevuKaynagi::getUrl('create'),
            'notUrl' => NotKaynagi::getUrl('create'),
        ];
    }
}
