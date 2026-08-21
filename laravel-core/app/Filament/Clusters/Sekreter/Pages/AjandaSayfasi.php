<?php

namespace App\Filament\Clusters\Sekreter\Pages;

use App\Filament\Clusters\Sekreter as SekreterCluster;
use App\Filament\Clusters\Sekreter\Resources\GorevKaynagi;
use App\Filament\Clusters\Sekreter\Resources\RandevuKaynagi;
use App\Filament\Support\HasTenantVisibility;
use App\Models\SekreterGorevi;
use App\Models\SekreterRandevusu;
use App\Services\SekreterHatirlatmaServisi;
use App\Support\SekreterYetkiSablonlari;
use Carbon\Carbon;
use Filament\Pages\Page;

class AjandaSayfasi extends Page
{
    use HasTenantVisibility;
    protected static ?string $cluster = SekreterCluster::class;
    protected static ?string $slug = 'ajanda';
    protected static ?string $title = 'Ajanda';
    protected static ?string $navigationLabel = 'Ajanda';
    protected static ?string $navigationGroup = 'Ajanda ve Görevler';
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.clusters.sekreter.pages.ajanda';
    protected static string $modulKodu = 'sekreter';
    protected static string $goruntuleYetkiKodu = SekreterYetkiSablonlari::GORUNTULE;

    /** Sekreter gezinmesi özel sidebar üzerinden yönetilir. */
    public function getSubNavigation(): array
    {
        return [];
    }

    protected function getViewData(): array
    {
        $gorunum = in_array(request()->query('gorunum'), ['gun', 'hafta', 'ay'], true) ? request()->query('gorunum') : 'ay';
        $baslangic = match ($gorunum) {
            'gun' => today()->startOfDay(),
            'hafta' => today()->startOfWeek(),
            default => today()->startOfMonth(),
        };
        $bitis = match ($gorunum) {
            'gun' => today()->endOfDay(),
            'hafta' => today()->endOfWeek(),
            default => today()->endOfMonth(),
        };

        $hatirlatma = app(SekreterHatirlatmaServisi::class);
        $gorevler = [];
        SekreterGorevi::query()
            ->where('tarih', '<=', $bitis->toDateString())
            ->get([
                'id', 'firma_id', 'olusturan_kullanici_id', 'atanan_kullanici_id', 'atanan_personel_id',
                'cari_id', 'baslik', 'aciklama', 'tarih', 'saat', 'durum', 'oncelik',
                'hatirlatma_tipi', 'tekrar_tipi', 'created_at', 'updated_at', 'deleted_at',
            ])
            ->each(function (SekreterGorevi $gorev) use (&$gorevler, $hatirlatma, $baslangic, $bitis): void {
                foreach ($hatirlatma->araliktakiEtkinlikler($gorev, $baslangic, $bitis) as $etkinlik) {
                    $gorevler[] = $etkinlik;
                }
            });

        $randevular = [];
        SekreterRandevusu::query()
            ->where('baslangic_tarihi', '<=', $bitis->toDateString())
            ->get([
                'id', 'firma_id', 'olusturan_kullanici_id', 'cari_id', 'baslik',
                'baslangic_tarihi', 'baslangic_saati', 'bitis_tarihi', 'bitis_saati',
                'aciklama', 'hatirlatma_tipi', 'tekrar_tipi', 'created_at', 'updated_at', 'deleted_at',
            ])
            ->each(function (SekreterRandevusu $randevu) use (&$randevular, $hatirlatma, $baslangic, $bitis): void {
                foreach ($hatirlatma->araliktakiEtkinlikler($randevu, $baslangic, $bitis) as $etkinlik) {
                    $randevular[] = $etkinlik;
                }
            });
        usort($gorevler, static fn (array $a, array $b): int => $a['zaman']->getTimestamp() <=> $b['zaman']->getTimestamp());
        usort($randevular, static fn (array $a, array $b): int => $a['zaman']->getTimestamp() <=> $b['zaman']->getTimestamp());

        return [
            'gorunum' => $gorunum,
            'baslik' => match ($gorunum) {
                'gun' => today()->translatedFormat('d F Y'),
                'hafta' => today()->startOfWeek()->format('d.m.Y').' - '.today()->endOfWeek()->format('d.m.Y'),
                default => today()->translatedFormat('F Y'),
            },
            'gorevler' => $gorevler,
            'randevular' => $randevular,
            'gorevUrl' => GorevKaynagi::getUrl('create'),
            'randevuUrl' => RandevuKaynagi::getUrl('create'),
        ];
    }
}
