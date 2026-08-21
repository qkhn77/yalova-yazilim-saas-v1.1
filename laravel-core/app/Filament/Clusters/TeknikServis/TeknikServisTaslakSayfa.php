<?php

namespace App\Filament\Clusters\TeknikServis;

use App\Filament\Clusters\TeknikServis as TeknikServisCluster;
use Filament\Pages\Page;

/**
 * Veri sorgusu icermeyen Teknik Servis kumesi sayfa iskeleti.
 */
abstract class TeknikServisTaslakSayfa extends Page
{
    protected static ?string $cluster = TeknikServisCluster::class;

    protected static string $view = 'filament.clusters.teknik-servis.pages.taslak';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'aciklama' => static::taslakAciklamasi(),
        ];
    }

    protected static function taslakAciklamasi(): string
    {
        return "Bu ekran yap\u{0131}sal iskelettir. \u{0130}\u{015F} mant\u{0131}\u{011F}\u{0131} ve entegrasyonlar sonraki a\u{015F}amalarda eklenecektir.";
    }
}
