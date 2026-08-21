<?php

namespace App\Filament\Clusters\TeknikServis\Resources\Concerns;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

abstract class HizliTanimOlusturSayfasi extends Page
{
    protected static string $view = 'filament.clusters.teknik-servis.resources.hizli-tanim-olustur';

    /** @var array<string,mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->data = [
            'firma_id' => null,
            'cihaz_id' => null,
            'ad' => '',
            'kod' => '',
            'aktif' => true,
            'siralama' => 0,
            'varsayilan_mi' => false,
            ...array_fill_keys(array_keys($this->bayrakAlanlari()), false),
        ];
    }

    public function create(): void
    {
        $kurallar = [
            'data.ad' => ['required', 'string', 'max:191'],
            'data.kod' => ['nullable', 'string', 'max:64'],
            'data.aktif' => ['nullable', 'boolean'],
            'data.siralama' => ['nullable', 'integer'],
            'data.varsayilan_mi' => ['nullable', 'boolean'],
        ];

        if ($this->cihazSecenekleri() !== []) {
            $kurallar['data.cihaz_id'] = ['nullable', 'integer'];
        }

        foreach (array_keys($this->bayrakAlanlari()) as $alan) {
            $kurallar['data.'.$alan] = ['nullable', 'boolean'];
        }

        $veri = $this->validate($kurallar)['data'];
        $model = $this->getModel();
        $alanlar = [
            'firma_id' => null,
            'ad' => trim((string) $veri['ad']),
            'kod' => trim((string) ($veri['kod'] ?? '')) ?: null,
            'aktif' => (bool) ($veri['aktif'] ?? false),
            'siralama' => (int) ($veri['siralama'] ?? 0),
            'varsayilan_mi' => (bool) ($veri['varsayilan_mi'] ?? false),
        ];

        if ($this->cihazSecenekleri() !== []) {
            $alanlar['cihaz_id'] = (int) ($veri['cihaz_id'] ?? 0) ?: null;
        }

        foreach (array_keys($this->bayrakAlanlari()) as $alan) {
            $alanlar[$alan] = (bool) ($veri[$alan] ?? false);
        }

        $model::query()->create($alanlar);

        Notification::make()->title('Tanım oluşturuldu')->success()->send();

        $this->redirect(static::getResource()::getUrl('index'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('index')
                ->label('Listeye dön')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => static::getResource()::getUrl('index')),
        ];
    }

    /**
     * @return array<int,string>
     */
    public function cihazSecenekleri(): array
    {
        return [];
    }

    /**
     * @return array<string,string>
     */
    public function bayrakAlanlari(): array
    {
        return [];
    }
}
