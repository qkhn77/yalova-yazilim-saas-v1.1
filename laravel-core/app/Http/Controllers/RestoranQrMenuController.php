<?php

namespace App\Http\Controllers;

use App\Models\Firma;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Restoran\RestoranMasasi;
use App\Models\Sube;
use App\Services\Restoran\RestoranQrMenuServisi;
use App\Services\Restoran\RestoranQrSiparisServisi;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestoranQrMenuController extends Controller
{
    public function goster(Request $request, string $firmaKodu, RestoranQrMenuServisi $qrMenuServisi): JsonResponse
    {
        $firma = Firma::query()
            ->where('firma_kodu', $firmaKodu)
            ->where('durum', Firma::DURUM_AKTIF)
            ->where('onaylandi_mi', true)
            ->firstOrFail();

        $subeId = $this->subeIdDogrula($request, (int) $firma->id);
        $menu = $qrMenuServisi->gorunurMenu((int) $firma->id, $subeId);

        return response()->json([
            'firma' => $this->firmaDizisi($firma),
            'sube_id' => $subeId,
            'kategoriler' => $this->menuDizisi($menu),
        ]);
    }

    public function masaMenusu(
        string $firmaKodu,
        string $masaQrKodu,
        RestoranQrMenuServisi $qrMenuServisi,
        RestoranQrSiparisServisi $qrSiparisServisi
    ): JsonResponse {
        $baglam = $qrSiparisServisi->masaBaglami($firmaKodu, $masaQrKodu);
        /** @var Firma $firma */
        $firma = $baglam['firma'];
        /** @var RestoranMasasi $masa */
        $masa = $baglam['masa'];
        $menu = $qrMenuServisi->gorunurMenu((int) $firma->id, $masa->sube_id ? (int) $masa->sube_id : null);
        $adisyon = $qrSiparisServisi->aktifAdisyon($firmaKodu, $masaQrKodu);

        return response()->json([
            'firma' => $this->firmaDizisi($firma),
            'masa' => $this->masaDizisi($masa),
            'kategoriler' => $this->menuDizisi($menu),
            'adisyon' => $this->adisyonDizisi($adisyon),
            'kalemler' => $this->kalemlerDizisi($adisyon),
        ]);
    }

    public function siparisEkle(
        Request $request,
        string $firmaKodu,
        string $masaQrKodu,
        RestoranQrSiparisServisi $qrSiparisServisi
    ): JsonResponse {
        $data = $request->validate([
            'menu_urunu_id' => ['required', 'integer', 'min:1'],
            'miktar' => ['nullable', 'numeric', 'min:0.0001', 'max:20'],
            'mutfak_notu' => ['nullable', 'string', 'max:300'],
        ]);

        $sonuc = $qrSiparisServisi->urunEkle(
            $firmaKodu,
            $masaQrKodu,
            (int) $data['menu_urunu_id'],
            (float) ($data['miktar'] ?? 1),
            $data['mutfak_notu'] ?? null,
        );

        $adisyon = $sonuc['adisyon']->refresh();
        $kalem = $sonuc['kalem']->refresh();

        return response()->json([
            'adisyon' => [
                'id' => (int) $adisyon->id,
                'adisyon_no' => $adisyon->adisyon_no,
                'durum' => $adisyon->durum,
                'genel_toplam' => number_format((float) $adisyon->genel_toplam, 2, '.', ''),
            ],
            'kalem' => [
                'id' => (int) $kalem->id,
                'urun_adi' => $kalem->urun_adi,
                'miktar' => number_format((float) $kalem->miktar, 4, '.', ''),
                'toplam_tutar' => number_format((float) $kalem->toplam_tutar, 2, '.', ''),
                'durum' => $kalem->durum,
            ],
        ], 201);
    }

    public function aktifAdisyon(
        string $firmaKodu,
        string $masaQrKodu,
        RestoranQrSiparisServisi $qrSiparisServisi
    ): JsonResponse {
        $adisyon = $qrSiparisServisi->aktifAdisyon($firmaKodu, $masaQrKodu);

        if (! $adisyon) {
            return response()->json([
                'adisyon' => null,
                'kalemler' => [],
            ]);
        }

        return response()->json([
            'adisyon' => $this->adisyonDizisi($adisyon),
            'kalemler' => $this->kalemlerDizisi($adisyon),
        ]);
    }

    public function kalemIptalEt(
        string $firmaKodu,
        string $masaQrKodu,
        int $kalemId,
        RestoranQrSiparisServisi $qrSiparisServisi
    ): JsonResponse {
        $adisyon = $qrSiparisServisi->kalemIptalEt($firmaKodu, $masaQrKodu, $kalemId);

        return response()->json([
            'adisyon' => $this->adisyonDizisi($adisyon),
            'kalemler' => $this->kalemlerDizisi($adisyon),
        ]);
    }

    /**
     * @return array{id: int, ad: string|null, firma_kodu: string|null}
     */
    private function firmaDizisi(Firma $firma): array
    {
        return [
            'id' => (int) $firma->id,
            'ad' => $firma->ad,
            'firma_kodu' => $firma->firma_kodu,
        ];
    }

    /**
     * @return array{id: int, ad: string|null, kod: string|null, sube_id: int|null}
     */
    private function masaDizisi(RestoranMasasi $masa): array
    {
        return [
            'id' => (int) $masa->id,
            'ad' => $masa->ad,
            'kod' => $masa->kod,
            'sube_id' => $masa->sube_id ? (int) $masa->sube_id : null,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $menu
     */
    private function menuDizisi(Collection $menu): Collection
    {
        return $menu->map(static fn ($kategori): array => [
            'id' => (int) $kategori->id,
            'ad' => $kategori->ad,
            'slug' => $kategori->slug,
            'siralama' => (int) $kategori->siralama,
            'urunler' => $kategori->urunler->map(static fn ($urun): array => [
                'id' => (int) $urun->id,
                'ad' => $urun->ad,
                'aciklama' => $urun->aciklama,
                'fiyat' => number_format((float) $urun->fiyat, 2, '.', ''),
                'kdv_orani' => number_format((float) $urun->kdv_orani, 2, '.', ''),
                'gorsel_yolu' => $urun->gorsel_yolu,
                'siralama' => (int) $urun->siralama,
            ])->values(),
        ])->values();
    }

    /**
     * @return array{id: int, adisyon_no: string|null, durum: string|null, genel_toplam: string}|null
     */
    private function adisyonDizisi(?RestoranAdisyonu $adisyon): ?array
    {
        if (! $adisyon) {
            return null;
        }

        return [
            'id' => (int) $adisyon->id,
            'adisyon_no' => $adisyon->adisyon_no,
            'durum' => $adisyon->durum,
            'genel_toplam' => number_format((float) $adisyon->genel_toplam, 2, '.', ''),
        ];
    }

    private function kalemlerDizisi(?RestoranAdisyonu $adisyon): Collection
    {
        if (! $adisyon) {
            return collect();
        }

        return $adisyon->kalemler->map(static fn ($kalem): array => [
            'id' => (int) $kalem->id,
            'urun_adi' => $kalem->urun_adi,
            'miktar' => number_format((float) $kalem->miktar, 4, '.', ''),
            'toplam_tutar' => number_format((float) $kalem->toplam_tutar, 2, '.', ''),
            'durum' => $kalem->durum,
        ])->values();
    }

    private function subeIdDogrula(Request $request, int $firmaId): ?int
    {
        if (! $request->filled('sube_id')) {
            return null;
        }

        $subeId = (int) $request->integer('sube_id');
        if ($subeId <= 0) {
            abort(404);
        }

        $subeVarMi = Sube::withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('id', $subeId)
            ->where('aktif_mi', true)
            ->exists();

        if (! $subeVarMi) {
            abort(404);
        }

        return $subeId;
    }
}
