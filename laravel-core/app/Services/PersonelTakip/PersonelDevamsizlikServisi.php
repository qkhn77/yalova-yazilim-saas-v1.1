<?php

namespace App\Services\PersonelTakip;

use App\Models\Firma;
use App\Models\Personel\PersonelGirisCikisi;
use App\Models\Personel\PersonelIzni;
use App\Models\Personel\PersonelVardiyasi;
use App\Models\Scopes\FirmaIdTenantScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class PersonelDevamsizlikServisi
{
    /**
     * @return array{islenen_vardiya:int, olusturulan_devamsizlik:int, atlanan:int}
     */
    public function firmaIcinIsle(int $firmaId, ?string $tarih = null): array
    {
        $hedefTarih = $tarih ? Carbon::parse($tarih)->toDateString() : now()->subDay()->toDateString();
        $simdi = now();

        $ozet = [
            'islenen_vardiya' => 0,
            'olusturulan_devamsizlik' => 0,
            'atlanan' => 0,
        ];

        PersonelVardiyasi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereDate('tarih', $hedefTarih)
            ->where('durum', '!=', 'iptal')
            ->whereNotNull('baslangic_at')
            ->whereNotNull('bitis_at')
            ->where('bitis_at', '<=', $simdi)
            ->orderBy('id')
            ->chunkById(100, function ($vardiyalar) use (&$ozet): void {
                foreach ($vardiyalar as $vardiya) {
                    $ozet['islenen_vardiya']++;

                    if (! $vardiya instanceof PersonelVardiyasi || $this->vardiyaIcinKayitVarMi($vardiya) || $this->izinCakismasiVarMi($vardiya)) {
                        $ozet['atlanan']++;
                        continue;
                    }

                    $this->devamsizlikOlustur($vardiya);
                    $ozet['olusturulan_devamsizlik']++;
                }
            });

        return $ozet;
    }

    /**
     * @return array{firma_sayisi:int, islenen_vardiya:int, olusturulan_devamsizlik:int, atlanan:int}
     */
    public function tumFirmalarIcinIsle(?string $tarih = null): array
    {
        $ozet = [
            'firma_sayisi' => 0,
            'islenen_vardiya' => 0,
            'olusturulan_devamsizlik' => 0,
            'atlanan' => 0,
        ];

        Firma::query()
            ->where('durum', Firma::DURUM_AKTIF)
            ->orderBy('id')
            ->chunkById(50, function ($firmalar) use (&$ozet, $tarih): void {
                foreach ($firmalar as $firma) {
                    $firmaOzeti = $this->firmaIcinIsle((int) $firma->id, $tarih);
                    $ozet['firma_sayisi']++;
                    $ozet['islenen_vardiya'] += $firmaOzeti['islenen_vardiya'];
                    $ozet['olusturulan_devamsizlik'] += $firmaOzeti['olusturulan_devamsizlik'];
                    $ozet['atlanan'] += $firmaOzeti['atlanan'];
                }
            });

        return $ozet;
    }

    private function vardiyaIcinKayitVarMi(PersonelVardiyasi $vardiya): bool
    {
        return PersonelGirisCikisi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $vardiya->firma_id)
            ->where('personel_id', $vardiya->personel_id)
            ->where(function ($query) use ($vardiya): void {
                $query->where('vardiya_id', $vardiya->id)
                    ->orWhereBetween('giris_at', [$vardiya->baslangic_at, $vardiya->bitis_at]);
            })
            ->exists();
    }

    private function izinCakismasiVarMi(PersonelVardiyasi $vardiya): bool
    {
        return PersonelIzni::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $vardiya->firma_id)
            ->where('personel_id', $vardiya->personel_id)
            ->where(function ($query): void {
                $query->where('durum', 'onaylandi')
                    ->orWhere('onay_durumu', 'onaylandi');
            })
            ->where('baslangic_at', '<', $vardiya->bitis_at)
            ->where('bitis_at', '>', $vardiya->baslangic_at)
            ->exists();
    }

    private function devamsizlikOlustur(PersonelVardiyasi $vardiya): PersonelIzni
    {
        return DB::transaction(function () use ($vardiya): PersonelIzni {
            if ($this->vardiyaIcinKayitVarMi($vardiya) || $this->izinCakismasiVarMi($vardiya)) {
                return PersonelIzni::query()
                    ->withoutGlobalScope(FirmaIdTenantScope::class)
                    ->where('firma_id', $vardiya->firma_id)
                    ->where('personel_id', $vardiya->personel_id)
                    ->where('izin_turu', 'devamsizlik')
                    ->where('baslangic_at', $vardiya->baslangic_at)
                    ->where('bitis_at', $vardiya->bitis_at)
                    ->firstOrFail();
            }

            return PersonelIzni::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->create([
                    'firma_id' => $vardiya->firma_id,
                    'personel_id' => $vardiya->personel_id,
                    'izin_turu' => 'devamsizlik',
                    'baslangic_at' => $vardiya->baslangic_at,
                    'bitis_at' => $vardiya->bitis_at,
                    'saat_sayisi' => round(Carbon::parse($vardiya->baslangic_at)->diffInMinutes(Carbon::parse($vardiya->bitis_at)) / 60, 2),
                    'durum' => 'onaylandi',
                    'onay_durumu' => 'onaylandi',
                    'aciklama' => 'Otomatik devamsızlık kaydı - vardiya #'.$vardiya->id,
                ]);
        });
    }
}
