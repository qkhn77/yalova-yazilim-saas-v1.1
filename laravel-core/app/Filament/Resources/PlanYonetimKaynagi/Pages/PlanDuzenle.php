<?php

namespace App\Filament\Resources\PlanYonetimKaynagi\Pages;

use App\Filament\Resources\PlanYonetimKaynagi;
use App\Models\Plan;
use App\Support\DenetimYardimcisi;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;

class PlanDuzenle extends EditRecord
{
    protected static string $resource = PlanYonetimKaynagi::class;

    protected static string $view = 'filament.resources.plan-yonetim-kaynagi.pages.plan-duzenle';

    public function form(Form $form): Form
    {
        if (PlanYonetimKaynagi::detayModu()) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (PlanYonetimKaynagi::detayModu()) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'aktif_mi' => (bool) ($this->record->aktif_mi ?? true),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (PlanYonetimKaynagi::detayModu()) {
            parent::save($shouldRedirect, $shouldSendSavedNotification);

            return;
        }

        $this->authorizeAccess();

        $this->record->forceFill([
            'aktif_mi' => (bool) ($this->data['aktif_mi'] ?? false),
        ])->save();

        $this->afterSave();

        if ($shouldSendSavedNotification) {
            $this->getSavedNotification()?->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $detayModu = PlanYonetimKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? PlanYonetimKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
        ];
    }

    protected function getFormActions(): array
    {
        if (PlanYonetimKaynagi::detayModu()) {
            return parent::getFormActions();
        }

        return [
            Actions\Action::make('save')
                ->label('Kaydet')
                ->action('save')
                ->color('primary'),
        ];
    }

    protected function afterSave(): void
    {
        DenetimYardimcisi::kaydet(
            'plan_guncellendi',
            Plan::class,
            (int) $this->record->id,
            null,
            null,
            $this->record->only(['ad', 'kod', 'ucret', 'sure_gun', 'aktif_mi'])
        );
    }
}
