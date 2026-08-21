<?php

namespace App\Services\PersonelTakip;

use App\Models\Personel\PersonelAyari;
use App\Models\Scopes\FirmaIdTenantScope;
use Illuminate\Support\Facades\Cache;

final class PersonelAyarlariServisi
{
    public const ANAHTAR_GENEL = 'genel';

    /**
     * @return array<string, mixed>
     */
    public function genel(int $firmaId): array
    {
        return Cache::remember($this->cacheAnahtari($firmaId), now()->addMinutes(5), function () use ($firmaId): array {
            $kayit = PersonelAyari::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->where('firma_id', $firmaId)
                ->where('anahtar', self::ANAHTAR_GENEL)
                ->first();

            return array_merge($this->varsayilanlar(), is_array($kayit?->deger) ? $kayit->deger : []);
        });
    }

    /**
     * @param  array<string, mixed>  $ayarlar
     * @return array<string, mixed>
     */
    public function kaydetGenel(int $firmaId, array $ayarlar): array
    {
        $temiz = $this->normalize($ayarlar);

        PersonelAyari::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->updateOrCreate(
                ['firma_id' => $firmaId, 'anahtar' => self::ANAHTAR_GENEL],
                ['deger' => $temiz]
            );

        Cache::forget($this->cacheAnahtari($firmaId));

        return $temiz;
    }

    /**
     * @return array<string, mixed>
     */
    public function varsayilanlar(): array
    {
        return [
            'para_birimi' => 'TRY',
            'gunluk_calisma_saati' => 7.5,
            'haftalik_calisma_saati' => 45,
            'fazla_mesai_katsayi' => 1.5,
            'pin_zorunlu' => false,
            'otomatik_maas_hesaplama' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $ayarlar
     * @return array<string, mixed>
     */
    private function normalize(array $ayarlar): array
    {
        $varsayilan = $this->varsayilanlar();
        $paraBirimi = strtoupper((string) ($ayarlar['para_birimi'] ?? $varsayilan['para_birimi']));
        if (! in_array($paraBirimi, ['TRY', 'USD', 'EUR'], true)) {
            $paraBirimi = 'TRY';
        }

        return [
            'para_birimi' => $paraBirimi,
            'gunluk_calisma_saati' => round(max(0, (float) ($ayarlar['gunluk_calisma_saati'] ?? $varsayilan['gunluk_calisma_saati'])), 2),
            'haftalik_calisma_saati' => round(max(0, (float) ($ayarlar['haftalik_calisma_saati'] ?? $varsayilan['haftalik_calisma_saati'])), 2),
            'fazla_mesai_katsayi' => round(max(1, (float) ($ayarlar['fazla_mesai_katsayi'] ?? $varsayilan['fazla_mesai_katsayi'])), 2),
            'pin_zorunlu' => (bool) ($ayarlar['pin_zorunlu'] ?? false),
            'otomatik_maas_hesaplama' => (bool) ($ayarlar['otomatik_maas_hesaplama'] ?? false),
        ];
    }

    private function cacheAnahtari(int $firmaId): string
    {
        return 'personel_ayarlari:genel:'.$firmaId;
    }
}
