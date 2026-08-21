<?php

namespace App\Filament\Resources\FirmaKullaniciGrubuKaynagi\Pages;

use App\Filament\Resources\FirmaKullaniciGrubuKaynagi;
use App\Services\TenantContextService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateFirmaKullaniciGrubu extends CreateRecord
{
    protected static string $resource = FirmaKullaniciGrubuKaynagi::class;

    protected function handleRecordCreation(array $data): Model
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            throw ValidationException::withMessages([
                'ad' => 'Geçerli firma bulunamadı.',
            ]);
        }

        return static::getModel()::query()->create([
            'ad' => trim((string) ($data['ad'] ?? '')),
            'kod' => FirmaKullaniciGrubuKaynagi::firmaKodluGrupKodu((string) ($data['ad'] ?? ''), $firmaId),
            'aciklama' => $data['aciklama'] ?? null,
            'sistem_rolu_mu' => false,
        ]);
    }
}
