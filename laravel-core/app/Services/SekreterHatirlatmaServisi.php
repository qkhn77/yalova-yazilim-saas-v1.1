<?php

namespace App\Services;

use App\Models\SekreterGorevi;
use App\Models\SekreterRandevusu;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SekreterHatirlatmaServisi
{
    /** @var array<string, int> */
    private const DAKIKALAR = [
        'etkinlik' => 0,
        '5_dk' => 5,
        '15_dk' => 15,
        '30_dk' => 30,
        '1_saat' => 60,
        '1_gun' => 1440,
        '1_hafta' => 10080,
    ];

    public function dakika(string $tip): ?int
    {
        return self::DAKIKALAR[$tip] ?? null;
    }

    public function etkinlikZamani(Model $kayit, ?Carbon $referans = null): Carbon
    {
        $zaman = $kayit instanceof SekreterGorevi
            ? Carbon::parse($kayit->tarih->format('Y-m-d').' '.($kayit->saat?->format('H:i:s') ?? '09:00:00'))
            : Carbon::parse($kayit->baslangic_tarihi->format('Y-m-d').' '.($kayit->baslangic_saati?->format('H:i:s') ?? '00:00:00'));

        $tekrar = (string) $kayit->tekrar_tipi;
        if ($tekrar === 'yok') {
            return $zaman;
        }

        $simdi = ($referans ?: now())->copy();
        if ($zaman->greaterThan($simdi)) {
            return $zaman;
        }

        return match ($tekrar) {
            'gunluk' => $zaman->addDays(max(0, $zaman->copy()->startOfDay()->diffInDays($simdi->copy()->startOfDay()))),
            'haftalik' => $zaman->addWeeks(max(0, intdiv($zaman->copy()->startOfDay()->diffInDays($simdi->copy()->startOfDay()), 7))),
            'aylik' => $zaman->addMonthsNoOverflow(max(0, $zaman->copy()->startOfMonth()->diffInMonths($simdi->copy()->startOfMonth()))),
            'yillik' => $zaman->addYearsNoOverflow(max(0, $zaman->copy()->startOfYear()->diffInYears($simdi->copy()->startOfYear()))),
            default => $zaman,
        };
    }

    public function hatirlatmaZamani(Model $kayit, ?Carbon $referans = null): ?Carbon
    {
        $dakika = $this->dakika((string) $kayit->hatirlatma_tipi);
        if ($dakika === null) {
            return null;
        }

        return $this->etkinlikZamani($kayit, $referans)->subMinutes($dakika);
    }

    public function kaynakTuru(Model $kayit): string
    {
        return $kayit instanceof SekreterGorevi ? 'sekreter.gorev' : 'sekreter.randevu';
    }

    public function hatirlanabilirModel(Model $kayit): string
    {
        return $kayit::class;
    }

    /** @return array<int, array{kayit: Model, zaman: Carbon}> */
    public function araliktakiEtkinlikler(Model $kayit, Carbon $baslangic, Carbon $bitis): array
    {
        $zaman = $kayit instanceof SekreterGorevi
            ? Carbon::parse($kayit->tarih->format('Y-m-d').' '.($kayit->saat?->format('H:i:s') ?? '09:00:00'))
            : Carbon::parse($kayit->baslangic_tarihi->format('Y-m-d').' '.($kayit->baslangic_saati?->format('H:i:s') ?? '00:00:00'));
        $tekrar = (string) $kayit->tekrar_tipi;

        if ($tekrar === 'yok') {
            return $zaman->betweenIncluded($baslangic, $bitis) ? [['kayit' => $kayit, 'zaman' => $zaman]] : [];
        }

        $sonuclar = [];
        $sayac = 0;
        if ($zaman->lt($baslangic)) {
            $zaman = match ($tekrar) {
                'gunluk' => $zaman->copy()->addDays($zaman->copy()->startOfDay()->diffInDays($baslangic->copy()->startOfDay())),
                'haftalik' => $zaman->copy()->addWeeks(intdiv($zaman->copy()->startOfDay()->diffInDays($baslangic->copy()->startOfDay()), 7)),
                'aylik' => $zaman->copy()->addMonthsNoOverflow($zaman->copy()->startOfMonth()->diffInMonths($baslangic->copy()->startOfMonth())),
                'yillik' => $zaman->copy()->addYearsNoOverflow($zaman->copy()->startOfYear()->diffInYears($baslangic->copy()->startOfYear())),
                default => $zaman,
            };
        }
        while ($zaman->lt($baslangic) && $sayac < 1000) {
            $zaman = $this->sonrakiTekrar($zaman, $tekrar);
            $sayac++;
        }
        while ($zaman->lte($bitis) && $sayac < 1000) {
            if ($zaman->gte($baslangic)) {
                $sonuclar[] = ['kayit' => $kayit, 'zaman' => $zaman->copy()];
            }
            $zaman = $this->sonrakiTekrar($zaman, $tekrar);
            $sayac++;
        }

        return $sonuclar;
    }

    private function sonrakiTekrar(Carbon $zaman, string $tekrar): Carbon
    {
        return match ($tekrar) {
            'gunluk' => $zaman->copy()->addDay(),
            'haftalik' => $zaman->copy()->addWeek(),
            'aylik' => $zaman->copy()->addMonthNoOverflow(),
            'yillik' => $zaman->copy()->addYearNoOverflow(),
            default => $zaman->copy()->addYears(100),
        };
    }
}
