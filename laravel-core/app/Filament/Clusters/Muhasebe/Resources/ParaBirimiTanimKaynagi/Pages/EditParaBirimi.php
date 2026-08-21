<?php

namespace App\Filament\Clusters\Muhasebe\Resources\ParaBirimiTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\ParaBirimiTanimKaynagi;
use App\Models\Muhasebe\ParaBirimi;
use App\Services\TenantContextService;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EditParaBirimi extends EditRecord
{
    protected static string $resource = ParaBirimiTanimKaynagi::class;

    protected static string $view = 'filament.clusters.muhasebe.resources.para-birimi-tanim-kaynagi.pages.edit-para-birimi';

    protected static ?string $title = 'Para birimi düzenle';

    public function form(Form $form): Form
    {
        if (ParaBirimiTanimKaynagi::detayModu()) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (ParaBirimiTanimKaynagi::detayModu()) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'aktif_mi' => (bool) ($this->record->aktif_mi ?? true),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (ParaBirimiTanimKaynagi::detayModu()) {
            parent::save($shouldRedirect, $shouldSendSavedNotification);

            return;
        }

        $this->authorizeAccess();

        $this->handleRecordUpdate($this->record, $this->mutateFormDataBeforeSave([
            'aktif_mi' => (bool) ($this->data['aktif_mi'] ?? false),
        ]));

        if ($shouldSendSavedNotification) {
            $this->getSavedNotification()?->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $detayModu = ParaBirimiTanimKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hizli Form' : 'Detaylar')
                ->url(fn (): string => $detayModu
                    ? ParaBirimiTanimKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [
                Actions\DeleteAction::make(),
            ] : []),
        ];
    }

    protected function getFormActions(): array
    {
        if (ParaBirimiTanimKaynagi::detayModu()) {
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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! ParaBirimiTanimKaynagi::detayModu()) {
            $data = array_replace($this->record->only([
                'firma_id',
                'is_sabit',
                'kod',
                'ad',
                'aktif_mi',
            ]), $data);
        }

        $kullanici = Auth::user();
        $super = $kullanici && ParaBirimi::kullaniciSuperAdminMi($kullanici);

        if (! $super) {
            if ($this->record->is_sabit) {
                abort(403);
            }
            $aktif = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
            if ($aktif < 1 || (int) $this->record->firma_id !== $aktif) {
                abort(403);
            }
            $data['firma_id'] = $aktif;
            $data['is_sabit'] = false;
        } else {
            $data['is_sabit'] = (bool) ($data['is_sabit'] ?? $this->record->is_sabit);
            if ($data['is_sabit']) {
                $data['firma_id'] = null;
            } else {
                $data['firma_id'] = (int) ($data['firma_id'] ?? $this->record->firma_id);
                if ($data['firma_id'] < 1) {
                    throw ValidationException::withMessages(['firma_id' => 'Firma geçersiz.']);
                }
            }
        }

        $kod = Str::upper(trim((string) ($data['kod'] ?? '')));
        if (strlen($kod) !== 3 || ! ctype_alpha($kod)) {
            throw ValidationException::withMessages(['kod' => 'Kod tam 3 harf olmalıdır (örn. TRY).']);
        }

        $kapsam = ($data['is_sabit'] ?? false) ? 0 : (int) $data['firma_id'];

        $var = ParaBirimi::tenantScopeOlmadan(fn () => ParaBirimi::query()
            ->where('tanim_firma_kapsami', $kapsam)
            ->whereRaw('UPPER(kod) = ?', [$kod])
            ->whereKeyNot($this->record->getKey())
            ->exists());
        if ($var) {
            throw ValidationException::withMessages(['kod' => 'Bu kod bu kapsamda zaten tanımlı.']);
        }

        $data['kod'] = $kod;
        $data['aktif_mi'] = (bool) ($data['aktif_mi'] ?? true);

        return $data;
    }
}
