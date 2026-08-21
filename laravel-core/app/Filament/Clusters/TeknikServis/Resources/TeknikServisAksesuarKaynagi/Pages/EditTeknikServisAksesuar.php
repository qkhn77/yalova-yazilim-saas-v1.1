<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisAksesuarKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisAksesuarKaynagi;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;

class EditTeknikServisAksesuar extends EditRecord
{
    protected static string $resource = TeknikServisAksesuarKaynagi::class;

    protected static string $view = 'filament.clusters.teknik-servis.resources.teknik-servis-aksesuar-kaynagi.pages.edit-teknik-servis-aksesuar';

    public function form(Form $form): Form
    {
        if (TeknikServisAksesuarKaynagi::detayModu()) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (TeknikServisAksesuarKaynagi::detayModu()) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'aktif' => (bool) ($this->record->aktif ?? true),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (TeknikServisAksesuarKaynagi::detayModu()) {
            parent::save($shouldRedirect, $shouldSendSavedNotification);

            return;
        }

        $this->authorizeAccess();

        $this->record->forceFill([
            'aktif' => (bool) ($this->data['aktif'] ?? false),
        ])->save();

        if ($shouldSendSavedNotification) {
            $this->getSavedNotification()?->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $detayModu = TeknikServisAksesuarKaynagi::detayModu();

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? TeknikServisAksesuarKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [
                Actions\DeleteAction::make(),
            ] : []),
        ];
    }

    protected function getFormActions(): array
    {
        if (TeknikServisAksesuarKaynagi::detayModu()) {
            return parent::getFormActions();
        }

        return [
            Actions\Action::make('save')
                ->label('Kaydet')
                ->action('save')
                ->color('primary'),
        ];
    }
}
