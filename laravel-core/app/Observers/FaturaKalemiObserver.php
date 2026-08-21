<?php

namespace App\Observers;

use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\StokKarti;
use App\Services\AuditTrailServisi;
use App\Support\DenetimYardimcisi;

class FaturaKalemiObserver
{
    public function __construct(
        private readonly AuditTrailServisi $auditTrailServisi,
    ) {}

    public function created(FaturaKalemi $kalem): void
    {
        DenetimYardimcisi::kaydet(
            olay: 'fatura_kalemi.eklendi',
            konuTipi: FaturaKalemi::class,
            konuId: (int) $kalem->getKey(),
            firmaId: (int) $kalem->firma_id,
            eskiVeri: null,
            yeniVeri: $this->izlenenVeri($kalem)
        );
    }

    public function updated(FaturaKalemi $kalem): void
    {
        $this->auditTrailServisi->modelDegisimiKaydet(
            olay: 'fatura_kalemi.guncelle',
            model: $kalem,
            eski: $kalem->getOriginal(),
            yeni: $kalem->getAttributes(),
            izlenenAlanlar: ['stok_id', 'aciklama', 'miktar', 'birim_fiyat', 'kdv_orani', 'indirim_orani', 'satir_indirim_tutari', 'satir_toplami', 'toplam'],
            ekBaglam: $this->stokBaglami((int) $kalem->stok_id)
        );
    }

    public function deleted(FaturaKalemi $kalem): void
    {
        DenetimYardimcisi::kaydet(
            olay: 'fatura_kalemi.silindi',
            konuTipi: FaturaKalemi::class,
            konuId: (int) $kalem->getKey(),
            firmaId: (int) $kalem->firma_id,
            eskiVeri: $this->izlenenVeri($kalem, true),
            yeniVeri: null
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function izlenenVeri(FaturaKalemi $kalem, bool $orijinal = false): array
    {
        $data = $orijinal ? $kalem->getOriginal() : $kalem->getAttributes();
        $stokId = (int) ($data['stok_id'] ?? 0);

        return array_merge([
            'fatura_id' => (int) ($data['fatura_id'] ?? 0),
            'stok_karti_id' => $stokId > 0 ? $stokId : null,
            'aciklama' => (string) ($data['aciklama'] ?? ''),
            'miktar' => (string) ($data['miktar'] ?? '0'),
            'birim_fiyat' => (string) ($data['birim_fiyat'] ?? '0'),
            'kdv_orani' => (string) ($data['kdv_orani'] ?? '0'),
            'iskonto_orani' => (string) ($data['indirim_orani'] ?? '0'),
            'iskonto_tutari' => (string) ($data['satir_indirim_tutari'] ?? '0'),
            'satir_toplami' => (string) ($data['satir_toplami'] ?? $data['toplam'] ?? '0'),
        ], $this->stokBaglami($stokId));
    }

    /**
     * @return array{stok_kodu?:string,urun_adi_snapshot?:string}
     */
    private function stokBaglami(int $stokId): array
    {
        if ($stokId <= 0) {
            return [];
        }

        $stok = StokKarti::query()->withoutGlobalScopes()->whereKey($stokId)->first(['kod', 'ad']);
        if (! $stok) {
            return [];
        }

        return [
            'stok_kodu' => (string) ($stok->kod ?? ''),
            'urun_adi_snapshot' => (string) ($stok->ad ?? ''),
        ];
    }
}
