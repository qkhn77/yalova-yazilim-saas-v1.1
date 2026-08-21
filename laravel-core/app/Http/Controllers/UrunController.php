<?php

namespace App\Http\Controllers;

use App\Models\Muhasebe\StokKategorisi;
use App\Models\Ecommerce\EcommerceMesajKonu;
use App\Support\EcommerceMesajTanimlari;
use App\Modules\Urun\Servisler\UrunServisi;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UrunController extends Controller
{
    public function __construct(
        private readonly UrunServisi $urunServisi,
    ) {}

    public function index(Request $request)
    {
        $filtreler = $this->urunFiltreleriniHazirla($request);

        $urunler = $this->urunServisi->listele($filtreler);
        $kategoriAgaci = $this->kategoriAgaciHazirla();
        $seciliKategoriSlug = is_string($filtreler['kategori']) && $filtreler['kategori'] !== ''
            ? (string) $filtreler['kategori']
            : null;

        return view('front.urunler.index', [
            'urunler' => $urunler,
            'kategoriAgaci' => $kategoriAgaci,
            'seciliKategoriSlug' => $seciliKategoriSlug,
            'filtreler' => $filtreler,
        ]);
    }

    public function show(string $slug)
    {
        $urun = $this->urunServisi->detay($slug);
        $benzerUrunler = collect();

        if ((int) ($urun->kategori_id ?? 0) > 0) {
            $benzerUrunler = $this->urunServisi->listele([
                'kategori_id' => (int) $urun->kategori_id,
                'siralama' => 'yeni',
                'per_page' => 8,
            ])->getCollection()
                ->reject(fn ($item) => (int) $item->id === (int) $urun->id)
                ->take(4)
                ->values();
        }

        $urunMesajlari = EcommerceMesajKonu::query()
            ->where('firma_id', (int) $urun->firma_id)
            ->where('konu_tipi', EcommerceMesajTanimlari::KONU_TIPI_URUN)
            ->where('stok_karti_id', (int) $urun->id)
            ->where('visible_on_product', true)
            ->with(['mesajlar' => function ($q) {
                $q->where('ic_not_mu', false)->orderBy('created_at');
            }])
            ->latest('updated_at')
            ->take(20)
            ->get();

        return view('front.urunler.detay', [
            'urun' => $urun,
            'benzerUrunler' => $benzerUrunler,
            'urunMesajlari' => $urunMesajlari,
        ]);
    }

    public function kategori(string $slug)
    {
        $kat = StokKategorisi::tenantScopeOlmadan(fn () => StokKategorisi::query()
            ->where('slug', $slug)
            ->where('aktif_mi', true)
            ->first());

        if (! $kat) {
            throw new NotFoundHttpException;
        }

        $filtreler = $this->urunFiltreleriniHazirla(request());
        $filtreler['kategori_id'] = (int) $kat->getKey();
        $filtreler['kategori'] = $kat->slug;

        $urunler = $this->urunServisi->listele($filtreler);

        return view('front.urunler.kategori', [
            'kategori' => $kat,
            'urunler' => $urunler,
            'kategoriAgaci' => $this->kategoriAgaciHazirla(),
            'seciliKategoriSlug' => $kat->slug,
            'filtreler' => $filtreler,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function urunFiltreleriniHazirla(Request $request): array
    {
        return [
            'kategori' => $request->query('kategori'),
            'fiyat_min' => $request->query('fiyat_min'),
            'fiyat_max' => $request->query('fiyat_max'),
            'arama' => $request->query('arama'),
            'stokta_var' => $request->query('stokta_var'),
            'siralama' => $request->query('siralama', 'yeni'),
            'page' => $request->query('page', 1),
            'per_page' => 12,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function kategoriAgaciHazirla(): array
    {
        $kategoriler = $this->urunServisi->aktifKategorileriGetir();
        $childrenByParent = [];

        foreach ($kategoriler as $kategori) {
            $parentId = $kategori->parent_id ? (int) $kategori->parent_id : 0;
            $childrenByParent[$parentId] ??= [];
            $childrenByParent[$parentId][] = $kategori;
        }

        return $this->kategoriDallariniOlustur($childrenByParent, 0, 1);
    }

    /**
     * @param  array<int, Collection<int, StokKategorisi>|array<int, StokKategorisi>>  $childrenByParent
     * @return array<int, array<string, mixed>>
     */
    private function kategoriDallariniOlustur(array $childrenByParent, int $parentId, int $level): array
    {
        if ($level > 4) {
            return [];
        }

        $children = $childrenByParent[$parentId] ?? [];
        $dallar = [];

        foreach ($children as $kategori) {
            $dallar[] = [
                'id' => (int) $kategori->id,
                'ad' => (string) $kategori->ad,
                'slug' => (string) $kategori->slug,
                'level' => $level,
                'children' => $this->kategoriDallariniOlustur($childrenByParent, (int) $kategori->id, $level + 1),
            ];
        }

        return $dallar;
    }
}
