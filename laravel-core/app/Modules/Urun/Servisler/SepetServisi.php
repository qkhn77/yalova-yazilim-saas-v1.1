<?php

namespace App\Modules\Urun\Servisler;

use App\Models\Ecommerce\Sepet;
use App\Models\Ecommerce\SepetKalemi;
use App\Models\Muhasebe\StokKarti;
use App\Services\EcommerceKampanyaServisi;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SepetServisi
{
    private const SESSION_SEPET_ID = 'aktif_sepet_id';
    private const SESSION_KUPON_KODU = 'aktif_kupon_kodu';

    public function __construct(
        private readonly EcommerceKampanyaServisi $kampanyaServisi,
    ) {}

    public function sepetiGetirVeyaOlustur(Request $request): Sepet
    {
        $request->session()->start();
        $oturumId = $request->session()->getId();
        $kullaniciId = Auth::id();
        $sessionSepetId = (int) $request->session()->get(self::SESSION_SEPET_ID, 0);
        $sessionSepet = $sessionSepetId > 0 ? Sepet::query()->find($sessionSepetId) : null;

        if ($kullaniciId) {
            $kullaniciSepeti = Sepet::query()
                ->where('kullanici_id', $kullaniciId)
                ->first();

            $oturumSepeti = $sessionSepet;
            if (! $oturumSepeti) {
                $oturumSepeti = Sepet::query()
                    ->whereNull('kullanici_id')
                    ->where('oturum_id', $oturumId)
                    ->first();
            }

            if (! $kullaniciSepeti) {
                $kullaniciSepeti = Sepet::query()->create([
                    'kullanici_id' => $kullaniciId,
                    'oturum_id' => $oturumId,
                    'son_aktif_at' => now(),
                ]);
            }

            if ($oturumSepeti && $oturumSepeti->id !== $kullaniciSepeti->id) {
                foreach ($oturumSepeti->kalemler as $kalem) {
                    $this->sepeteEkle($request, (int) $kalem->stok_karti_id, (float) $kalem->miktar, $kullaniciSepeti);
                }
                $oturumSepeti->delete();
            }

            $kullaniciSepeti->update(['oturum_id' => $oturumId, 'son_aktif_at' => now()]);
            $request->session()->put(self::SESSION_SEPET_ID, $kullaniciSepeti->id);

            return $this->sepetKalemleriniTazele($kullaniciSepeti);
        }

        if ($sessionSepet && $sessionSepet->kullanici_id === null) {
            $sessionSepet->update(['oturum_id' => $oturumId, 'son_aktif_at' => now()]);

            return $this->sepetKalemleriniTazele($sessionSepet);
        }

        $sepet = Sepet::query()->firstOrCreate(
            ['kullanici_id' => null, 'oturum_id' => $oturumId],
            ['son_aktif_at' => now()]
        );
        $request->session()->put(self::SESSION_SEPET_ID, $sepet->id);

        return $this->sepetKalemleriniTazele($sepet);
    }

    public function sepeteEkle(Request $request, int $stokKartiId, float $miktar = 1, ?Sepet $sepet = null): Sepet
    {
        $sepet ??= $this->sepetiGetirVeyaOlustur($request);
        $miktar = max(1, $miktar);

        $stokKarti = StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()
            ->whereKey($stokKartiId)
            ->where('tur', StokKartiTuru::ETicaret->value)
            ->where('durum', HesapDurumu::Aktif->value)
            ->first());

        if (! $stokKarti) {
            throw ValidationException::withMessages([
                'stok' => 'Ürün bulunamadı veya satışa uygun değil.',
            ]);
        }

        $mevcutKalem = SepetKalemi::query()
            ->where('sepet_id', $sepet->id)
            ->where('stok_karti_id', $stokKartiId)
            ->first();

        $toplamMiktar = $miktar + (float) ($mevcutKalem?->miktar ?? 0);
        $this->stokKontrolEt($stokKarti, $toplamMiktar);

        $birimFiyat = $this->stokKartiBazBirimFiyati($stokKarti);
        $paraBirimi = $this->stokKartiParaBirimi($stokKarti);
        $satirToplami = round($birimFiyat * $toplamMiktar, 2);

        if ($mevcutKalem) {
            $mevcutKalem->update([
                'urun_adi_snapshot' => (string) $stokKarti->ad,
                'urun_kodu_snapshot' => (string) ($stokKarti->kod ?? ''),
                'birim_fiyat' => $birimFiyat,
                'para_birimi' => $paraBirimi,
                'kdv_orani' => (float) ($stokKarti->kdv_orani ?? 0),
                'miktar' => $toplamMiktar,
                'satir_toplami' => $satirToplami,
            ]);
        } else {
            SepetKalemi::query()->create([
                'sepet_id' => $sepet->id,
                'stok_karti_id' => $stokKarti->id,
                'urun_adi_snapshot' => (string) $stokKarti->ad,
                'urun_kodu_snapshot' => (string) ($stokKarti->kod ?? ''),
                'birim_fiyat' => $birimFiyat,
                'para_birimi' => $paraBirimi,
                'kdv_orani' => (float) ($stokKarti->kdv_orani ?? 0),
                'miktar' => $toplamMiktar,
                'satir_toplami' => $satirToplami,
            ]);
        }

        $sepet->update(['son_aktif_at' => now()]);

        return $this->sepetKalemleriniTazele($sepet->fresh());
    }

    public function kalemMiktarGuncelle(Request $request, int $kalemId, float $miktar): Sepet
    {
        $sepet = $this->sepetiGetirVeyaOlustur($request);
        $kalem = $this->sepetKalemiGetir($sepet, $kalemId);
        $miktar = max(1, $miktar);

        $stokKarti = StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()->find($kalem->stok_karti_id));
        if (! $stokKarti) {
            throw ValidationException::withMessages(['stok' => 'Ürün artık mevcut değil.']);
        }

        $this->stokKontrolEt($stokKarti, $miktar);

        $birimFiyat = $this->stokKartiBazBirimFiyati($stokKarti);
        $kalem->update([
            'urun_adi_snapshot' => (string) $stokKarti->ad,
            'urun_kodu_snapshot' => (string) ($stokKarti->kod ?? ''),
            'birim_fiyat' => $birimFiyat,
            'para_birimi' => $this->stokKartiParaBirimi($stokKarti),
            'kdv_orani' => (float) ($stokKarti->kdv_orani ?? 0),
            'miktar' => $miktar,
            'satir_toplami' => round($birimFiyat * $miktar, 2),
        ]);

        $sepet->update(['son_aktif_at' => now()]);

        return $this->sepetKalemleriniTazele($sepet->fresh());
    }

    public function kalemSil(Request $request, int $kalemId): Sepet
    {
        $sepet = $this->sepetiGetirVeyaOlustur($request);
        $kalem = $this->sepetKalemiGetir($sepet, $kalemId);
        $kalem->delete();

        return $this->sepetKalemleriniTazele($sepet->fresh());
    }

    public function sepetiBosalt(Sepet $sepet): void
    {
        SepetKalemi::query()->where('sepet_id', $sepet->id)->delete();
        $sepet->update(['son_aktif_at' => now()]);
    }

    public function aktifSepetiBosaltVeOturumuTemizle(Request $request): void
    {
        $request->session()->start();

        $sepetId = (int) $request->session()->get(self::SESSION_SEPET_ID, 0);
        if ($sepetId > 0) {
            $sepet = Sepet::query()->find($sepetId);
            if ($sepet) {
                $this->sepetiBosalt($sepet);
            }
        }

        $request->session()->put('aktif_sepet_urun_adedi', 0);
        $request->session()->forget('cart_recently_added');
    }

    public function toplamlar(Sepet $sepet, ?string $kuponKodu = null, ?int $kullaniciId = null): array
    {
        return $this->kampanyaServisi->toplamlariHesapla($sepet, $kuponKodu, $kullaniciId);
    }

    public function kuponKoduGetir(Request $request): ?string
    {
        $kupon = (string) $request->session()->get(self::SESSION_KUPON_KODU, '');

        return $kupon !== '' ? $kupon : null;
    }

    public function kuponKoduKaydet(Request $request, ?string $kuponKodu): void
    {
        $normal = $this->normalizeKuponKodu($kuponKodu);
        if ($normal === null) {
            $request->session()->forget(self::SESSION_KUPON_KODU);

            return;
        }

        $request->session()->put(self::SESSION_KUPON_KODU, $normal);
    }

    public function kuponKoduTemizle(Request $request): void
    {
        $request->session()->forget(self::SESSION_KUPON_KODU);
    }

    private function normalizeKuponKodu(?string $kuponKodu): ?string
    {
        if (! is_string($kuponKodu)) {
            return null;
        }

        $kuponKodu = strtoupper(trim($kuponKodu));

        return $kuponKodu !== '' ? $kuponKodu : null;
    }

    private function stokKontrolEt(StokKarti $stokKarti, float $istenenMiktar): void
    {
        if (! (bool) $stokKarti->stok_takip) {
            return;
        }

        if ($stokKarti->musaitStokMiktari() < $istenenMiktar) {
            throw ValidationException::withMessages([
                'stok' => 'Yetersiz stok. Bu ürün için yeterli miktar bulunmuyor.',
            ]);
        }
    }

    private function sepetKalemiGetir(Sepet $sepet, int $kalemId): SepetKalemi
    {
        $kalem = SepetKalemi::query()
            ->where('sepet_id', $sepet->id)
            ->whereKey($kalemId)
            ->first();

        if (! $kalem) {
            throw ValidationException::withMessages([
                'sepet' => 'Sepet kalemi bulunamadı.',
            ]);
        }

        return $kalem;
    }

    private function stokKartiBazBirimFiyati(StokKarti $stokKarti): float
    {
        return round((float) ($stokKarti->indirimli_fiyat ?: $stokKarti->satis_fiyati), 2);
    }

    private function stokKartiParaBirimi(StokKarti $stokKarti): string
    {
        return strtoupper((string) ($stokKarti->para_birimi ?: 'TRY'));
    }

    private function sepetKalemleriniTazele(Sepet $sepet): Sepet
    {
        $sepet->load('kalemler.stokKarti');

        foreach ($sepet->kalemler as $kalem) {
            $stokKarti = $kalem->stokKarti;
            if (! $stokKarti) {
                continue;
            }

            $birimFiyat = $this->stokKartiBazBirimFiyati($stokKarti);
            $satirToplami = round($birimFiyat * (float) $kalem->miktar, 2);
            $paraBirimi = $this->stokKartiParaBirimi($stokKarti);

            $degisecek = (
                round((float) $kalem->birim_fiyat, 2) !== $birimFiyat
                || round((float) $kalem->satir_toplami, 2) !== $satirToplami
                || strtoupper((string) ($kalem->getAttribute('para_birimi') ?: 'TRY')) !== $paraBirimi
                || (string) $kalem->urun_adi_snapshot !== (string) $stokKarti->ad
                || (string) ($kalem->urun_kodu_snapshot ?? '') !== (string) ($stokKarti->kod ?? '')
                || round((float) $kalem->kdv_orani, 2) !== round((float) ($stokKarti->kdv_orani ?? 0), 2)
            );

            if (! $degisecek) {
                continue;
            }

            $kalem->update([
                'urun_adi_snapshot' => (string) $stokKarti->ad,
                'urun_kodu_snapshot' => (string) ($stokKarti->kod ?? ''),
                'birim_fiyat' => $birimFiyat,
                'para_birimi' => $paraBirimi,
                'kdv_orani' => (float) ($stokKarti->kdv_orani ?? 0),
                'satir_toplami' => $satirToplami,
            ]);
        }

        return $sepet->fresh('kalemler.stokKarti');
    }
}
