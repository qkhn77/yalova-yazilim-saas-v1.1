<?php

namespace App\Filament\Resources\YetkiYonetimKaynagi\Pages;

use App\Filament\Resources\YetkiYonetimKaynagi;
use App\Models\Yetki;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class YetkiDuzenle extends EditRecord
{
    protected static string $resource = YetkiYonetimKaynagi::class;

    protected static string $view = 'filament.resources.yetki-yonetim-kaynagi.pages.yetki-duzenle';

    public function form(Form $form): Form
    {
        if (YetkiYonetimKaynagi::detayModu()) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (YetkiYonetimKaynagi::detayModu()) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'ad' => (string) ($this->record->ad ?? ''),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (YetkiYonetimKaynagi::detayModu()) {
            parent::save($shouldRedirect, $shouldSendSavedNotification);

            return;
        }

        $this->authorizeAccess();

        $ad = trim((string) ($this->data['ad'] ?? ''));
        if ($ad === '') {
            throw ValidationException::withMessages([
                'data.ad' => 'Ad zorunludur.',
            ]);
        }

        $this->record->forceFill([
            'ad' => $ad,
        ])->save();

        if ($shouldSendSavedNotification) {
            $this->getSavedNotification()?->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $detayModu = YetkiYonetimKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? YetkiYonetimKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
        ];
    }

    protected function getFormActions(): array
    {
        if (YetkiYonetimKaynagi::detayModu()) {
            return parent::getFormActions();
        }

        return [
            Actions\Action::make('save')
                ->label('Kaydet')
                ->action('save')
                ->color('primary'),
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (YetkiYonetimKaynagi::detayModu()) {
            return $data;
        }

        $alanlar = [
            'ad',
            'kod',
            'modul_kodu',
            'eylem',
        ];

        $mevcut = Yetki::query()
            ->whereKey($this->record->getKey())
            ->first($alanlar);

        if (! $mevcut) {
            return $data;
        }

        $mevcutVeri = array_intersect_key($mevcut->getAttributes(), array_flip($alanlar));

        return array_replace($mevcutVeri, $data);
    }
}
