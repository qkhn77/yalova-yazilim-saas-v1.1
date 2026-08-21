<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class TeknikServisKaydiHizliGoruntule extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TeknikServisKaydiKaynagi::class;

    protected static string $view = 'filament.clusters.teknik-servis.resources.teknik-servis-kaydi-kaynagi.pages.teknik-servis-kaydi-hizli-goruntule';

    public function mount(int|string $record): void
    {
        if (request()->boolean('detay')) {
            $this->redirect(TeknikServisKaydiKaynagi::getUrl('view-detail', [
                'record' => $record,
                'detay' => 1,
            ]));

            return;
        }

        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canView($this->getRecord()), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Servis kaydı';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
