<?php

namespace App\Services;

use App\Models\Ecommerce\EcommerceKampanya;
use App\Models\Ecommerce\EcommerceKampanyaKullanimi;
use App\Models\Ecommerce\Sepet;
use App\Models\Ecommerce\Siparis;
use App\Models\Muhasebe\StokKarti;
use App\Services\Front\FrontFiyatServisi;

class EcommerceKampanyaServisi
{
    public function __construct(
        private readonly FrontFiyatServisi $frontFiyatServisi,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toplamlariHesapla(Sepet $sepet, ?string $kuponKodu = null, ?int $kullaniciId = null): array
    {
        $hedefParaBirimi = $this->frontFiyatServisi->aktifParaBirimi();
        $araToplam = round((float) $sepet->kalemler->sum(function ($kalem): float {
            $kaynakPb = strtoupper((string) ($kalem->getAttribute('para_birimi') ?: 'TRY'));

            return $this->frontFiyatServisi->cevir((float) $kalem->satir_toplami, $kaynakPb, $this->frontFiyatServisi->aktifParaBirimi());
        }), 2);
        $indirim = 0.0;
        $secilenKampanya = null;

        $firmaId = $this->firmaIdBul($sepet);
        if ($firmaId !== null && $araToplam > 0) {
            $secim = $this->enAvantajliKampanyayiSec($sepet, $firmaId, $araToplam, $kuponKodu, $kullaniciId, $hedefParaBirimi);
            if ($secim !== null) {
                $indirim = round(min($araToplam, (float) ($secim['indirim_tutari'] ?? 0)), 2);
                $secilenKampanya = $secim['kampanya'];
            }
        }

        $kdvMatrahi = max(0, round($araToplam - $indirim, 2));
        $kdvToplam = (float) $sepet->kalemler->sum(function ($kalem) use ($araToplam, $kdvMatrahi, $hedefParaBirimi): float {
            $kaynakPb = strtoupper((string) ($kalem->getAttribute('para_birimi') ?: 'TRY'));
            $satir = $this->frontFiyatServisi->cevir((float) $kalem->satir_toplami, $kaynakPb, $hedefParaBirimi);
            if ($araToplam <= 0 || $satir <= 0) {
                return 0.0;
            }

            $dagilimOrani = $satir / $araToplam;
            $dagitilanSatir = $kdvMatrahi * $dagilimOrani;
            $oran = (float) ($kalem->kdv_orani ?? 0);

            return round($dagitilanSatir * ($oran / 100), 2);
        });

        return [
            'para_birimi' => $hedefParaBirimi,
            'ara_toplam' => round($araToplam, 2),
            'indirim_toplami' => round($indirim, 2),
            'kdv_toplam' => round($kdvToplam, 2),
            'genel_toplam' => round($kdvMatrahi + $kdvToplam, 2),
            'uygulanan_kampanya' => $secilenKampanya ? [
                'id' => (int) $secilenKampanya->id,
                'ad' => (string) $secilenKampanya->ad,
                'tip' => (string) $secilenKampanya->tip,
                'kupon_kodu' => (string) ($secilenKampanya->kupon_kodu ?? ''),
            ] : null,
        ];
    }

    public function kampanyaKullaniminiKaydet(Siparis $siparis): void
    {
        $kampanyaId = (int) ($siparis->kampanya_id ?? 0);
        if ($kampanyaId <= 0) {
            return;
        }

        $firmaId = (int) ($siparis->firma_id ?? 0);

        EcommerceKampanyaKullanimi::query()->create([
            'firma_id' => $firmaId > 0 ? $firmaId : 0,
            'kampanya_id' => $kampanyaId,
            'kullanici_id' => $siparis->kullanici_id,
            'siparis_id' => $siparis->id,
            'adet' => 1,
        ]);

        EcommerceKampanya::query()
            ->whereKey($kampanyaId)
            ->increment('kullanilan_adet', 1);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function enAvantajliKampanyayiSec(Sepet $sepet, int $firmaId, float $araToplam, ?string $kuponKodu, ?int $kullaniciId, string $hedefParaBirimi): ?array
    {
        $kupon = $this->normalizeKupon($kuponKodu);
        $today = now()->toDateString();

        $kampanyalar = EcommerceKampanya::query()
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->where(function ($query) use ($today): void {
                $query->where('suresiz_mi', true)
                    ->orWhere(function ($q) use ($today): void {
                        $q->whereDate('baslangic_tarihi', '<=', $today)
                            ->where(function ($qq) use ($today): void {
                                $qq->whereDate('bitis_tarihi', '>=', $today)
                                    ->orWhereNull('bitis_tarihi');
                            });
                    });
            })
            ->orderBy('oncelik')
            ->orderByDesc('id')
            ->get();

        $enIyi = null;

        foreach ($kampanyalar as $kampanya) {
            if (! $this->kampanyaUygunMu($kampanya, $sepet, $araToplam, $kupon, $kullaniciId, $hedefParaBirimi)) {
                continue;
            }

            $indirim = $this->indirimHesapla($kampanya, $sepet, $araToplam, $hedefParaBirimi);
            if ($indirim <= 0) {
                continue;
            }

            if ($enIyi === null || $indirim > (float) $enIyi['indirim_tutari']) {
                $enIyi = [
                    'kampanya' => $kampanya,
                    'indirim_tutari' => $indirim,
                ];
            }
        }

        return $enIyi;
    }

    private function kampanyaUygunMu(EcommerceKampanya $kampanya, Sepet $sepet, float $araToplam, ?string $kupon, ?int $kullaniciId, string $hedefParaBirimi): bool
    {
        $kampanyaParaBirimi = strtoupper((string) ($kampanya->para_birimi ?: 'TRY'));
        $minSepetTutari = $kampanya->min_sepet_tutari !== null
            ? $this->frontFiyatServisi->cevir((float) $kampanya->min_sepet_tutari, $kampanyaParaBirimi, $hedefParaBirimi)
            : null;

        if ($minSepetTutari !== null && $minSepetTutari > $araToplam) {
            return false;
        }

        if ((bool) $kampanya->kupon_gerekli) {
            if ($kupon === null) {
                return false;
            }

            if ($kupon !== $this->normalizeKupon((string) ($kampanya->kupon_kodu ?? ''))) {
                return false;
            }
        }

        if ($kampanya->sistem_geneli_limit !== null && (int) $kampanya->kullanilan_adet >= (int) $kampanya->sistem_geneli_limit) {
            return false;
        }

        if ($kampanya->kullanici_basi_limit !== null && $kullaniciId !== null && $kullaniciId > 0) {
            $kullaniciSayisi = (int) EcommerceKampanyaKullanimi::query()
                ->where('kampanya_id', $kampanya->id)
                ->where('kullanici_id', $kullaniciId)
                ->sum('adet');

            if ($kullaniciSayisi >= (int) $kampanya->kullanici_basi_limit) {
                return false;
            }
        }

        return $this->hedefUygunMu($kampanya, $sepet, $kullaniciId);
    }

    private function hedefUygunMu(EcommerceKampanya $kampanya, Sepet $sepet, ?int $kullaniciId): bool
    {
        $hedefTipi = (string) ($kampanya->hedef_tipi ?? 'genel');
        $hedefIdler = collect((array) ($kampanya->hedef_idler ?? []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();

        if ($hedefTipi === 'genel' || $hedefIdler->isEmpty()) {
            return true;
        }

        if ($hedefTipi === 'kullanici') {
            return $kullaniciId !== null && $hedefIdler->contains((int) $kullaniciId);
        }

        $stoklar = StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()
            ->whereIn('id', $sepet->kalemler->pluck('stok_karti_id')->all())
            ->get(['id', 'kategori_id']));

        if ($hedefTipi === 'urun') {
            return $stoklar->pluck('id')->contains(fn ($id): bool => $hedefIdler->contains((int) $id));
        }

        if ($hedefTipi === 'kategori') {
            return $stoklar->pluck('kategori_id')->filter()->contains(fn ($id): bool => $hedefIdler->contains((int) $id));
        }

        return true;
    }

    private function indirimHesapla(EcommerceKampanya $kampanya, Sepet $sepet, float $araToplam, string $hedefParaBirimi): float
    {
        $tip = (string) $kampanya->tip;

        if ($tip === 'yuzde') {
            $oran = max(0.0, min(100.0, (float) ($kampanya->indirim_orani ?? 0)));

            return round($araToplam * ($oran / 100), 2);
        }

        if ($tip === 'sabit_tutar') {
            $kampanyaParaBirimi = strtoupper((string) ($kampanya->para_birimi ?: 'TRY'));

            return round(max(0.0, $this->frontFiyatServisi->cevir((float) ($kampanya->indirim_tutari ?? 0), $kampanyaParaBirimi, $hedefParaBirimi)), 2);
        }

        if ($tip === 'x_al_y_ode') {
            $x = (int) ($kampanya->x_adet ?? 0);
            $y = (int) ($kampanya->y_adet ?? 0);
            if ($x <= 0 || $y <= 0 || $x <= $y) {
                return 0.0;
            }

            $toplamAdet = (int) floor((float) $sepet->kalemler->sum('miktar'));
            if ($toplamAdet < $x) {
                return 0.0;
            }

            $grupSayisi = intdiv($toplamAdet, $x);
            $bedavaAdet = ($x - $y) * $grupSayisi;
            if ($bedavaAdet <= 0) {
                return 0.0;
            }

            $enDusukBirimFiyat = (float) $sepet->kalemler->map(function ($kalem) use ($hedefParaBirimi): float {
                $kaynakPb = strtoupper((string) ($kalem->getAttribute('para_birimi') ?: 'TRY'));

                return $this->frontFiyatServisi->cevir((float) $kalem->birim_fiyat, $kaynakPb, $hedefParaBirimi);
            })->min();

            return round(max(0.0, $enDusukBirimFiyat) * $bedavaAdet, 2);
        }

        return 0.0;
    }

    private function firmaIdBul(Sepet $sepet): ?int
    {
        $ilkKalem = $sepet->kalemler->first();
        if (! $ilkKalem) {
            return null;
        }

        $firmaId = (int) StokKarti::tenantScopeOlmadan(fn () => (int) StokKarti::query()
            ->whereKey((int) $ilkKalem->stok_karti_id)
            ->value('firma_id'));

        return $firmaId > 0 ? $firmaId : null;
    }

    private function normalizeKupon(?string $kupon): ?string
    {
        if (! is_string($kupon)) {
            return null;
        }

        $kupon = strtoupper(trim($kupon));

        return $kupon !== '' ? $kupon : null;
    }
}
