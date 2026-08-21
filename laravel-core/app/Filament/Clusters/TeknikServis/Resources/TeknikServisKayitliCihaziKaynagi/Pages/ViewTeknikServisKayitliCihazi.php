<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKayitliCihaziKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKayitliCihaziKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ViewTeknikServisKayitliCihazi extends Page
{
    protected static string $resource = TeknikServisKayitliCihaziKaynagi::class;
    protected static string $view = 'filament.clusters.teknik-servis.resources.teknik-servis-kayitli-cihazi-kaynagi.pages.view-teknik-servis-kayitli-cihazi';

    public $record;

    public function mount(int|string $record): void
    {
        $this->record = TeknikServisKayitliCihaziKaynagi::resolveRecordRouteBinding($record);
        abort_unless($this->record && TeknikServisKayitliCihaziKaynagi::canView($this->record), 404);
        $this->record->load([
            'cari:id,ad,telefon,gsm', 'cihaz:id,ad', 'marka:id,ad',
            'servisKayitlari' => fn ($query) => $query->with('servisDurumu:id,ad')->latest('id'),
            'degisiklikler.kullanici:id,name,email',
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Kayıtlı cihaz '.$this->record->cihaz_no;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('yeni_servis')->label('Yeni servis kaydı')->icon('heroicon-o-plus-circle')->color('primary')
                ->url(fn (): string => TeknikServisKaydiKaynagi::getUrl('create_arizali', [
                    'cari_id' => $this->record->cari_id,
                    'kayitli_cihaz_id' => $this->record->getKey(),
                ])),
            Actions\Action::make('duzenle')->label('Düzenle')->icon('heroicon-o-pencil-square')
                ->url(fn (): string => TeknikServisKayitliCihaziKaynagi::getUrl('edit', ['record' => $this->record])),
        ];
    }
}
