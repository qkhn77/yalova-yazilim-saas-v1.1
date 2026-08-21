<?php

namespace App\TeknikServis\Filament;

use App\Filament\Clusters\Muhasebe\Pages\VadeTakipSayfasi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\TeknikServis\Servisler\TeknikServisAlacakOzetServisi;
use Filament\Actions\Action;

final class TeknikServisAlacakOzetAksiyonu
{
    /**
     * @param callable(): ?TeknikServisKaydi $servisGetir
     */
    public static function olustur(callable $servisGetir): Action
    {
        return Action::make('alacakTahsilatOzeti')
            ->label('Finans Özeti')
            ->icon('heroicon-o-banknotes')
            ->color(fn (): string => self::renk($servisGetir()))
            ->visible(fn (): bool => self::gorunurMu($servisGetir()))
            ->modalHeading('Alacak ve Tahsilat Özeti')
            ->modalWidth('7xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Kapat')
            ->modalContent(function () use ($servisGetir) {
                $servis = $servisGetir();

                return view('filament.clusters.teknik-servis.partials.alacak-tahsilat-ozeti', [
                    'ozet' => $servis
                        ? app(TeknikServisAlacakOzetServisi::class)->ozet($servis->fresh(['cari']) ?? $servis)
                        : [],
                    'vadeTakipUrl' => VadeTakipSayfasi::getUrl(),
                ]);
            });
    }

    private static function gorunurMu(?TeknikServisKaydi $servis): bool
    {
        return (bool) ($servis?->exists);
    }

    private static function renk(?TeknikServisKaydi $servis): string
    {
        if (! $servis || ! $servis->exists) {
            return 'gray';
        }

        $toplam = (float) ($servis->toplam_tutar ?? 0);
        if ($toplam <= 0.009) {
            return 'gray';
        }

        $odenen = (float) ($servis->odenen_tutar ?? 0);
        if ($toplam - $odenen > 0.009) {
            return 'warning';
        }

        return 'success';
    }

}
