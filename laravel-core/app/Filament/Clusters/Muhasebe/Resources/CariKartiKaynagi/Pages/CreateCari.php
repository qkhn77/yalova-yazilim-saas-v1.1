<?php

namespace App\Filament\Clusters\Muhasebe\Resources\CariKartiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\CariKartiKaynagi;
use App\Models\Muhasebe\Cari;
use App\Services\TenantContextService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateCari extends CreateRecord
{
    protected static string $resource = CariKartiKaynagi::class;

    protected static ?string $title = 'Cari ekle';

    /**
     * Kayıt tamamlandığında listeye değil, oluşturulan carinin detayına geçilir.
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', [
            'record' => $this->record,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $kullanici = Auth::user();
        $super = $kullanici && Cari::kullaniciSuperAdminMi($kullanici);

        if (! $super) {
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

        if (! CariKartiKaynagi::telefonBenzersizMi((int) $data['firma_id'], $data['telefon'] ?? null)) {
            throw ValidationException::withMessages(['telefon' => 'Bu telefon numarası aynı firma içinde başka bir cari kartında kayıtlı.']);
        }

        // Cari kodu kullanıcı girdisi değildir; model oluşturulurken firma sayacından üretilir.
        unset($data['kod']);

        $para = strtoupper(trim((string) ($data['para_birimi'] ?? '')));
        if ($para === '' || ! CariKartiKaynagi::paraBirimiFirmaIcinGecerliMi((int) $data['firma_id'], $para)) {
            throw ValidationException::withMessages([
                'para_birimi' => 'Para birimi bu firma için tanımlı ve aktif olmalıdır (Tanımlar → Para birimleri).',
            ]);
        }
        $data['para_birimi'] = $para;

        return $data;
    }
}
