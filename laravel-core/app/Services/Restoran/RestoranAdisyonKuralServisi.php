<?php

namespace App\Services\Restoran;

use App\Models\Personel\Personel;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Restoran\RestoranMasasi;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Models\Sube;
use App\Models\Muhasebe\Cari;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class RestoranAdisyonKuralServisi
{
    public function hazirlaVeDogrula(RestoranAdisyonu $adisyon): void
    {
        if (! $adisyon->firma_id) {
            return;
        }

        $adisyon->durum = $adisyon->durum ?: RestoranAdisyonu::DURUM_ACIK;
        $adisyon->acilis_at = $adisyon->acilis_at ?: now();
        $adisyon->para_birimi = strtoupper((string) ($adisyon->para_birimi ?: 'TRY'));
        $adisyon->siparis_tipi = $adisyon->siparis_tipi ?: 'masa';
        $adisyon->musteri_sayisi = max(1, (int) ($adisyon->musteri_sayisi ?: 1));
        $adisyon->servis_ucreti = max(0, (float) ($adisyon->servis_ucreti ?? 0));

        if (in_array((string) $adisyon->siparis_tipi, ['paket', 'online'], true) && ! $adisyon->paket_durum) {
            $adisyon->paket_durum = RestoranAdisyonu::PAKET_DURUM_HAZIRLANIYOR;
        }

        if (! $adisyon->adisyon_no) {
            $adisyon->adisyon_no = $this->sonrakiAdisyonNo((int) $adisyon->firma_id);
        }

        $hatalar = [];
        $masa = $this->masa($adisyon);
        $sube = $this->sube($adisyon);

        if ($adisyon->masa_id && ! $masa) {
            $hatalar['masa_id'][] = 'Seçilen masa bu firmaya ait değil.';
        }

        if ($adisyon->sube_id && ! $sube) {
            $hatalar['sube_id'][] = 'Seçilen şube bu firmaya ait değil.';
        }

        if ($adisyon->cari_id && ! $this->cariVarMi((int) $adisyon->firma_id, (int) $adisyon->cari_id)) {
            $hatalar['cari_id'][] = 'Seçilen cari bu firmaya ait değil.';
        }

        if ($masa && $sube && $masa->sube_id && (int) $masa->sube_id !== (int) $sube->id) {
            $hatalar['masa_id'][] = 'Seçilen masa adisyon şubesiyle uyumlu değil.';
        }

        if ($masa && ! $adisyon->sube_id && $masa->sube_id) {
            $adisyon->sube_id = $masa->sube_id;
        }

        foreach ([
            'garson_personel_id' => 'Garson',
            'kasiyer_personel_id' => 'Kasiyer',
            'kurye_personel_id' => 'Kurye',
        ] as $alan => $etiket) {
            $personelId = $adisyon->getAttribute($alan);
            if ($personelId && ! $this->aktifPersonelVarMi((int) $adisyon->firma_id, (int) $personelId, $adisyon->sube_id)) {
                $hatalar[$alan][] = "{$etiket} bu firma veya şube için uygun değil.";
            }
        }

        if (! in_array((string) $adisyon->durum, [
            RestoranAdisyonu::DURUM_ACIK,
            RestoranAdisyonu::DURUM_ODEMEDE,
            RestoranAdisyonu::DURUM_KAPANDI,
            RestoranAdisyonu::DURUM_IPTAL,
        ], true)) {
            $hatalar['durum'][] = 'Adisyon durumu geçerli değil.';
        }

        if (! in_array((string) $adisyon->siparis_tipi, ['masa', 'paket', 'gel-al', 'qr', 'online'], true)) {
            $hatalar['siparis_tipi'][] = 'Sipariş tipi geçerli değil.';
        }

        if ($adisyon->paket_durum && ! in_array((string) $adisyon->paket_durum, [
            RestoranAdisyonu::PAKET_DURUM_HAZIRLANIYOR,
            RestoranAdisyonu::PAKET_DURUM_KURYEE_ATANDI,
            RestoranAdisyonu::PAKET_DURUM_YOLDA,
            RestoranAdisyonu::PAKET_DURUM_TESLIM_EDILDI,
            RestoranAdisyonu::PAKET_DURUM_IPTAL,
        ], true)) {
            $hatalar['paket_durum'][] = 'Paket servis durumu geçerli değil.';
        }

        if ($adisyon->paket_durum && ! in_array((string) $adisyon->siparis_tipi, ['paket', 'online'], true)) {
            $hatalar['paket_durum'][] = 'Paket durumu sadece paket veya online siparişlerde kullanılabilir.';
        }

        if ($adisyon->kurye_personel_id && ! in_array((string) $adisyon->siparis_tipi, ['paket', 'online'], true)) {
            $hatalar['kurye_personel_id'][] = 'Kurye sadece paket veya online siparişlerde atanabilir.';
        }

        if ($adisyon->paket_durum === RestoranAdisyonu::PAKET_DURUM_TESLIM_EDILDI && ! $adisyon->teslimat_at) {
            $adisyon->teslimat_at = now();
        }

        if ($adisyon->kapanis_at && Carbon::parse($adisyon->kapanis_at)->lessThan(Carbon::parse($adisyon->acilis_at))) {
            $hatalar['kapanis_at'][] = 'Kapanış zamanı açılış zamanından önce olamaz.';
        }

        if ($masa && in_array((string) $adisyon->durum, [RestoranAdisyonu::DURUM_ACIK, RestoranAdisyonu::DURUM_ODEMEDE], true)
            && $this->masadaAcikAdisyonVarMi($adisyon)) {
            $hatalar['masa_id'][] = 'Bu masada açık bir adisyon zaten var.';
        }

        if ($hatalar !== []) {
            throw ValidationException::withMessages($hatalar);
        }
    }

    public function masaDurumunuGuncelle(RestoranAdisyonu $adisyon): void
    {
        if (! $adisyon->masa_id || ! $adisyon->firma_id) {
            return;
        }

        $masa = RestoranMasasi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $adisyon->firma_id)
            ->whereKey($adisyon->masa_id)
            ->first();

        if (! $masa) {
            return;
        }

        if (in_array((string) $adisyon->durum, [RestoranAdisyonu::DURUM_ACIK, RestoranAdisyonu::DURUM_ODEMEDE], true)) {
            if ($masa->durum !== RestoranMasasi::DURUM_DOLU) {
                $masa->forceFill(['durum' => RestoranMasasi::DURUM_DOLU])->saveQuietly();
            }

            return;
        }

        $acikVar = RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $adisyon->firma_id)
            ->where('masa_id', $adisyon->masa_id)
            ->whereIn('durum', [RestoranAdisyonu::DURUM_ACIK, RestoranAdisyonu::DURUM_ODEMEDE])
            ->exists();

        if (! $acikVar && $masa->durum === RestoranMasasi::DURUM_DOLU) {
            $masa->forceFill(['durum' => RestoranMasasi::DURUM_BOS])->saveQuietly();
        }
    }

    private function sonrakiAdisyonNo(int $firmaId): string
    {
        $yil = now()->format('Y');
        $adet = RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->where('adisyon_no', 'like', "AD-{$yil}-%")
            ->count() + 1;

        return 'AD-'.$yil.'-'.str_pad((string) $adet, 6, '0', STR_PAD_LEFT);
    }

    private function masa(RestoranAdisyonu $adisyon): ?RestoranMasasi
    {
        if (! $adisyon->masa_id) {
            return null;
        }

        return RestoranMasasi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $adisyon->firma_id)
            ->whereKey($adisyon->masa_id)
            ->first();
    }

    private function sube(RestoranAdisyonu $adisyon): ?Sube
    {
        if (! $adisyon->sube_id) {
            return null;
        }

        return Sube::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $adisyon->firma_id)
            ->whereKey($adisyon->sube_id)
            ->first();
    }

    private function aktifPersonelVarMi(int $firmaId, int $personelId, mixed $subeId): bool
    {
        return Personel::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereKey($personelId)
            ->where('durum', Personel::DURUM_AKTIF)
            ->when($subeId, function ($query) use ($subeId): void {
                $query->where(function ($inner) use ($subeId): void {
                    $inner->whereNull('sube_id')
                        ->orWhere('sube_id', $subeId);
                });
            })
            ->exists();
    }

    private function cariVarMi(int $firmaId, int $cariId): bool
    {
        return Cari::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereKey($cariId)
            ->exists();
    }

    private function masadaAcikAdisyonVarMi(RestoranAdisyonu $adisyon): bool
    {
        return RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $adisyon->firma_id)
            ->where('masa_id', $adisyon->masa_id)
            ->whereIn('durum', [RestoranAdisyonu::DURUM_ACIK, RestoranAdisyonu::DURUM_ODEMEDE])
            ->when($adisyon->exists, fn ($query) => $query->whereKeyNot($adisyon->getKey()))
            ->exists();
    }
}
