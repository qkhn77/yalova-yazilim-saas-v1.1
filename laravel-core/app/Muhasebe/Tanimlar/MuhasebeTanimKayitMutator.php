<?php

namespace App\Muhasebe\Tanimlar;

use App\Models\Muhasebe\MuhasebeMarka;
use App\Models\Muhasebe\MuhasebeStokModeli;
use App\Models\Muhasebe\VergiOrani;
use App\Services\TenantContextService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Standart muhasebe tanım kayıtları için create/save öncesi veri hazırlığı (firma / sabit, kod tekilliği).
 */
final class MuhasebeTanimKayitMutator
{
    /**
     * @param  array<string, mixed>  $data
     * @param  class-string<Model>  $modelSinifi
     * @return array<string, mixed>
     */
    public static function olustur(array $data, string $modelSinifi): array
    {
        return self::normalize($data, $modelSinifi, null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  class-string<Model>  $modelSinifi
     * @return array<string, mixed>
     */
    public static function guncelle(array $data, string $modelSinifi, Model $kayit): array
    {
        return self::normalize($data, $modelSinifi, $kayit);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  class-string<Model>  $modelSinifi
     * @return array<string, mixed>
     */
    private static function normalize(array $data, string $modelSinifi, ?Model $kayit): array
    {
        $kullanici = Auth::user();
        $super = $kullanici && method_exists($modelSinifi, 'kullaniciSuperAdminMi')
            && $modelSinifi::kullaniciSuperAdminMi($kullanici);

        if (! $super && $kayit !== null && (bool) $kayit->getAttribute('is_sabit')) {
            abort(403);
        }

        if (! $super && $kayit !== null) {
            $aktif = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
            if ($aktif < 1 || (int) $kayit->getAttribute('firma_id') !== $aktif) {
                abort(403);
            }
        }

        $data['is_sabit'] = $super ? (bool) ($data['is_sabit'] ?? ($kayit?->is_sabit ?? false)) : false;

        if ($data['is_sabit']) {
            $data['firma_id'] = null;
        } elseif (! $super) {
            $fid = app(TenantContextService::class)->aktifFirmaId();
            if (! $fid) {
                throw ValidationException::withMessages(['firma_id' => 'Aktif firma oturumu yok.']);
            }
            $data['firma_id'] = $fid;
        } else {
            $data['firma_id'] = (int) ($data['firma_id'] ?? $kayit?->firma_id ?? 0);
            if ($data['firma_id'] < 1) {
                throw ValidationException::withMessages(['firma_id' => 'Firma seçilmelidir.']);
            }
        }

        $kod = Str::upper(trim((string) ($data['kod'] ?? '')));
        if ($kod === '' || strlen($kod) > 64) {
            throw ValidationException::withMessages(['kod' => 'Kod zorunludur ve en fazla 64 karakter olabilir.']);
        }

        $data['kod'] = $kod;
        $data['aktif_mi'] = (bool) ($data['aktif_mi'] ?? true);

        if ($modelSinifi === VergiOrani::class) {
            $oran = isset($data['oran']) ? (float) $data['oran'] : null;
            if ($oran === null || $oran < 0 || $oran > 100) {
                throw ValidationException::withMessages(['oran' => 'Vergi oranı 0 ile 100 arasında olmalıdır.']);
            }
            $data['oran'] = round($oran, 4);
        }

        if ($modelSinifi === MuhasebeStokModeli::class) {
            $markaId = (int) ($data['marka_id'] ?? 0);
            if ($markaId < 1) {
                throw ValidationException::withMessages(['marka_id' => 'Marka seçilmelidir.']);
            }
            self::markaErisiminiDogrula($data['is_sabit'], (int) ($data['firma_id'] ?? 0), $markaId);
            $data['marka_id'] = $markaId;

            $kapsam = $data['is_sabit'] ? 0 : (int) $data['firma_id'];
            $sorgu = MuhasebeStokModeli::tenantScopeOlmadan(fn () => MuhasebeStokModeli::query()
                ->where('tanim_firma_kapsami', $kapsam)
                ->where('marka_id', $markaId)
                ->whereRaw('UPPER(kod) = ?', [$kod]));
            if ($kayit !== null) {
                $sorgu->whereKeyNot($kayit->getKey());
            }
            if ($sorgu->exists()) {
                throw ValidationException::withMessages(['kod' => 'Bu kod bu marka ve kapsamda zaten tanımlı.']);
            }
        } else {
            $kapsam = $data['is_sabit'] ? 0 : (int) $data['firma_id'];
            $sorgu = $modelSinifi::tenantScopeOlmadan(fn () => $modelSinifi::query()
                ->where('tanim_firma_kapsami', $kapsam)
                ->whereRaw('UPPER(kod) = ?', [$kod]));
            if ($kayit !== null) {
                $sorgu->whereKeyNot($kayit->getKey());
            }
            if ($sorgu->exists()) {
                throw ValidationException::withMessages(['kod' => 'Bu kod bu kapsamda zaten tanımlı.']);
            }
        }

        return $data;
    }

    private static function markaErisiminiDogrula(bool $stokModeliSabit, int $firmaId, int $markaId): void
    {
        $uygun = MuhasebeMarka::tenantScopeOlmadan(function () use ($stokModeliSabit, $firmaId, $markaId): bool {
            $q = MuhasebeMarka::query()->whereKey($markaId);
            if ($stokModeliSabit) {
                return $q->where('is_sabit', true)->whereNull('firma_id')->exists();
            }
            if ($firmaId < 1) {
                return false;
            }

            return $q->where(function ($w) use ($firmaId): void {
                $tablo = (new MuhasebeMarka)->getTable();
                $w->where($tablo.'.firma_id', $firmaId)
                    ->orWhere(function ($w2) use ($tablo): void {
                        $w2->whereNull($tablo.'.firma_id')
                            ->where($tablo.'.is_sabit', true);
                    });
            })->exists();
        });

        if (! $uygun) {
            throw ValidationException::withMessages(['marka_id' => 'Seçilen marka bu tanım için geçerli değil.']);
        }
    }
}
