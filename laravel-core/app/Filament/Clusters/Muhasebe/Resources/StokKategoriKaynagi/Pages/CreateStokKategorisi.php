<?php

namespace App\Filament\Clusters\Muhasebe\Resources\StokKategoriKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\StokKategoriKaynagi;
use App\Models\Muhasebe\StokKategorisi as StokKategoriModel;
use App\Services\TenantContextService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateStokKategorisi extends CreateRecord
{
    protected static string $resource = StokKategoriKaynagi::class;

    protected static ?string $title = 'Kategori ekle';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $kullanici = Auth::user();
        $super = $kullanici && StokKategoriModel::kullaniciSuperAdminMi($kullanici);

        $data['is_sabit'] = $super ? (bool) ($data['is_sabit'] ?? false) : false;

        if ($data['is_sabit']) {
            $data['firma_id'] = null;
        } elseif (! $super) {
            $fid = app(TenantContextService::class)->aktifFirmaId();
            if (! $fid) {
                throw ValidationException::withMessages(['firma_id' => 'Aktif firma oturumu yok.']);
            }
            $data['firma_id'] = $fid;
        } else {
            $data['firma_id'] = (int) ($data['firma_id'] ?? 0);
            if ($data['firma_id'] < 1) {
                throw ValidationException::withMessages(['firma_id' => 'Firma seçilmelidir.']);
            }
        }

        $kod = trim((string) ($data['kod'] ?? ''));
        $ad = trim((string) ($data['ad'] ?? ''));
        if ($kod === '' || $ad === '') {
            throw ValidationException::withMessages(['kod' => 'Kod ve ad zorunludur.']);
        }

        $kapsam = $data['is_sabit'] ? 0 : (int) $data['firma_id'];

        $varKod = StokKategoriModel::tenantScopeOlmadan(fn () => StokKategoriModel::query()
            ->where('tanim_firma_kapsami', $kapsam)
            ->where('kod', $kod)
            ->exists());
        if ($varKod) {
            throw ValidationException::withMessages(['kod' => 'Bu kod bu kapsamda zaten var.']);
        }

        $varAd = StokKategoriModel::tenantScopeOlmadan(fn () => StokKategoriModel::query()
            ->where('tanim_firma_kapsami', $kapsam)
            ->where('ad', $ad)
            ->exists());
        if ($varAd) {
            throw ValidationException::withMessages(['ad' => 'Bu ad bu kapsamda zaten var.']);
        }

        $data['kod'] = $kod;
        $data['ad'] = $ad;
        $data['parent_id'] = StokKategoriKaynagi::parentKategoriIdHazirla(
            $data['is_sabit'] ? null : (int) $data['firma_id'],
            (bool) $data['is_sabit'],
            (int) ($data['parent_id'] ?? 0),
            null
        );

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Stok kategorisi oluşturuldu.';
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title($this->getCreatedNotificationTitle())
            ->body('Yeni kategori başarıyla kaydedildi.');
    }
}
