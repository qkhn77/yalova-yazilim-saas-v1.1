<?php

namespace App\Filament\Clusters\TeklifYonetimi\Resources\TeklifSablonKaynagi\Pages;

use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifSablonKaynagi;
use App\Models\TeklifYonetimi\TeklifBaskiSablonu;
use App\TeklifYonetimi\Servisler\TeklifBaskiSablonuServisi;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;

class PreviewTeklifSablonu extends ViewRecord
{
    protected static string $resource = TeklifSablonKaynagi::class;

    protected static string $view = 'filament.clusters.teklif-yonetimi.resources.teklif-sablon-kaynagi.pages.preview-teklif-sablonu';

    protected static ?string $title = 'Son Kullanıcı Teklif Görünümü';

    private ?string $onizlemeHtmlCache = null;

    private ?string $onizlemeKapsayiciStiliCache = null;

    private ?string $onizlemeSagBoslukCache = null;

    public function getTitle(): string|Htmlable
    {
        /** @var TeklifBaskiSablonu $record */
        $record = $this->record;

        return new HtmlString(e((string) $record->ad));
    }

    protected function fillForm(): void
    {
        //
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function onizlemeHtml(): Htmlable
    {
        $this->onizlemeHtmlCache ??= Cache::remember(
            'teklif_sablon_onizleme_html|'.((int) $this->record->getKey()).'|'.((string) $this->record->updated_at),
            now()->addMinutes(10),
            fn (): string => app(TeklifBaskiSablonuServisi::class)
                ->onizlemeHtmlOlustur($this->record->toArray(), (int) $this->record->firma_id)
        );

        return new HtmlString($this->onizlemeHtmlCache);
    }

    public function onizlemeCss(): string
    {
        return (string) ($this->record->sablon_css ?? '');
    }

    public function onizlemeKapsayiciStili(): string
    {
        return $this->onizlemeKapsayiciStiliCache ??= app(TeklifBaskiSablonuServisi::class)
            ->kapsayiciStili((string) ($this->record->sayfa_tipi ?? 'a4'));
    }

    public function onizlemeSagBosluk(): string
    {
        return $this->onizlemeSagBoslukCache ??= app(TeklifBaskiSablonuServisi::class)
            ->sayfaSagBosluk((string) ($this->record->sayfa_tipi ?? 'a4'));
    }

    public function frameUrl(): string
    {
        return route('admin.teklif-yonetimi.sablonlar.preview-frame', [
            'sablon' => (int) $this->record->getKey(),
            'v' => (string) $this->record->updated_at,
        ]);
    }
}
