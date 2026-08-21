<?php

namespace App\Services\Restoran;

use App\Models\Firma;
use App\Models\Restoran\RestoranAdisyonKalemi;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Restoran\RestoranMasasi;
use App\Models\Restoran\RestoranMenuUrunu;
use App\Models\Scopes\FirmaIdTenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RestoranQrSiparisServisi
{
    /**
     * @return array{firma: Firma, masa: RestoranMasasi}
     */
    public function masaBaglami(string $firmaKodu, string $masaQrKodu): array
    {
        $firma = $this->aktifFirma($firmaKodu);
        $masa = $this->masa($firma, $masaQrKodu);

        return [
            'firma' => $firma,
            'masa' => $masa,
        ];
    }

    public function aktifAdisyon(string $firmaKodu, string $masaQrKodu): ?RestoranAdisyonu
    {
        $firma = $this->aktifFirma($firmaKodu);
        $masa = $this->masa($firma, $masaQrKodu);

        return RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->with(['kalemler' => function ($query): void {
                $query
                    ->withoutGlobalScope(FirmaIdTenantScope::class)
                    ->where('durum', '!=', RestoranAdisyonKalemi::DURUM_IPTAL)
                    ->orderBy('created_at')
                    ->orderBy('id');
            }])
            ->where('firma_id', $firma->id)
            ->where('masa_id', $masa->id)
            ->whereIn('durum', [RestoranAdisyonu::DURUM_ACIK, RestoranAdisyonu::DURUM_ODEMEDE])
            ->first();
    }

    /**
     * @return array{adisyon: RestoranAdisyonu, kalem: RestoranAdisyonKalemi}
     */
    public function urunEkle(
        string $firmaKodu,
        string $masaQrKodu,
        int $menuUrunuId,
        float $miktar = 1,
        ?string $mutfakNotu = null
    ): array {
        return DB::transaction(function () use ($firmaKodu, $masaQrKodu, $menuUrunuId, $miktar, $mutfakNotu): array {
            $firma = $this->aktifFirma($firmaKodu);
            $masa = $this->masa($firma, $masaQrKodu, true);
            $adisyon = $this->acikAdisyonVeyaYeni($firma, $masa);
            $menuUrunu = $this->menuUrunu($firma, $menuUrunuId);

            $kalem = app(RestoranSiparisKalemServisi::class)->menuUrunuEkle($adisyon, $menuUrunu, $miktar, $mutfakNotu);

            return [
                'adisyon' => $kalem->adisyon()->withoutGlobalScope(FirmaIdTenantScope::class)->firstOrFail(),
                'kalem' => $kalem,
            ];
        });
    }

    public function kalemIptalEt(string $firmaKodu, string $masaQrKodu, int $kalemId): RestoranAdisyonu
    {
        return DB::transaction(function () use ($firmaKodu, $masaQrKodu, $kalemId): RestoranAdisyonu {
            $firma = $this->aktifFirma($firmaKodu);
            $masa = $this->masa($firma, $masaQrKodu, true);
            $adisyon = $this->acikAdisyon($firma, $masa, true)->firstOrFail();
            $kalem = RestoranAdisyonKalemi::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->where('firma_id', $firma->id)
                ->where('adisyon_id', $adisyon->id)
                ->whereKey($kalemId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($kalem->durum !== RestoranAdisyonKalemi::DURUM_YENI) {
                throw ValidationException::withMessages([
                    'kalem_id' => 'Sadece mutfaga alinmamis yeni kalem iptal edilebilir.',
                ]);
            }

            $kalem->fill([
                'durum' => RestoranAdisyonKalemi::DURUM_IPTAL,
            ])->save();

            return $adisyon->refresh()->load(['kalemler' => function ($query): void {
                $query
                    ->withoutGlobalScope(FirmaIdTenantScope::class)
                    ->where('durum', '!=', RestoranAdisyonKalemi::DURUM_IPTAL)
                    ->orderBy('created_at')
                    ->orderBy('id');
            }]);
        });
    }

    private function aktifFirma(string $firmaKodu): Firma
    {
        return Firma::query()
            ->where('firma_kodu', $firmaKodu)
            ->where('durum', Firma::DURUM_AKTIF)
            ->where('onaylandi_mi', true)
            ->firstOrFail();
    }

    private function masa(Firma $firma, string $masaQrKodu, bool $kilitle = false): RestoranMasasi
    {
        $sorgu = RestoranMasasi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firma->id)
            ->where('qr_siparis_kodu', $masaQrKodu)
            ->where('aktif_mi', true);

        if ($kilitle) {
            $sorgu->lockForUpdate();
        }

        $masa = $sorgu->firstOrFail();

        if ($masa->durum === RestoranMasasi::DURUM_KAPALI) {
            throw ValidationException::withMessages([
                'masa' => 'Bu masa siparise kapali.',
            ]);
        }

        return $masa;
    }

    private function acikAdisyonVeyaYeni(Firma $firma, RestoranMasasi $masa): RestoranAdisyonu
    {
        $adisyon = $this->acikAdisyon($firma, $masa, true)->first();

        if ($adisyon) {
            return $adisyon;
        }

        return RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->create([
                'firma_id' => $firma->id,
                'sube_id' => $masa->sube_id,
                'masa_id' => $masa->id,
                'siparis_tipi' => 'qr',
                'musteri_sayisi' => 1,
                'para_birimi' => 'TRY',
            ]);
    }

    private function acikAdisyon(Firma $firma, RestoranMasasi $masa, bool $kilitle = false)
    {
        $sorgu = RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firma->id)
            ->where('masa_id', $masa->id)
            ->whereIn('durum', [RestoranAdisyonu::DURUM_ACIK, RestoranAdisyonu::DURUM_ODEMEDE]);

        if ($kilitle) {
            $sorgu->lockForUpdate();
        }

        return $sorgu;
    }

    private function menuUrunu(Firma $firma, int $menuUrunuId): RestoranMenuUrunu
    {
        return RestoranMenuUrunu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firma->id)
            ->whereKey($menuUrunuId)
            ->firstOrFail();
    }
}
