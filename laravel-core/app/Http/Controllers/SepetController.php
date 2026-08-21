<?php

namespace App\Http\Controllers;

use App\Models\Muhasebe\StokKarti;
use App\Models\Ecommerce\Sepet;
use App\Modules\Urun\Servisler\SepetServisi;
use App\Services\Front\FrontFiyatServisi;
use App\Support\UygulamaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SepetController extends Controller
{
    public function __construct(
        private readonly SepetServisi $sepetServisi,
        private readonly FrontFiyatServisi $frontFiyatServisi,
    ) {}

    public function index(Request $request)
    {
        $sepet = $this->sepetServisi->sepetiGetirVeyaOlustur($request);
        $sepet->loadMissing([
            'kalemler.stokKarti.gorseller',
        ]);
        $kuponKodu = $this->sepetServisi->kuponKoduGetir($request);

        return view('front.sepet.index', [
            'sepet' => $sepet,
            'kuponKodu' => $kuponKodu,
            'toplamlar' => $this->sepetServisi->toplamlar($sepet, $kuponKodu, auth()->id()),
        ]);
    }

    public function ekle(Request $request, string $slug): RedirectResponse|JsonResponse
    {
        $request->validate([
            'miktar' => ['nullable', 'numeric', 'min:1'],
        ]);

        $stok = StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()
            ->where('slug', $slug)
            ->first());

        if (! $stok) {
            return back()->withErrors(['stok' => 'Urun bulunamadi.']);
        }

        $sepet = $this->sepetServisi->sepeteEkle(
            request: $request,
            stokKartiId: (int) $stok->id,
            miktar: (float) ($request->input('miktar', 1))
        );

        $this->sepetSayisiniOtumdaTazele($request, $sepet);

        if ($request->expectsJson() || $request->ajax()) {
            $sepet->loadMissing('kalemler.stokKarti.gorseller');

            return response()->json([
                'success' => true,
                'message' => 'Urun sepete eklendi.',
                'cart_count' => (int) round((float) $sepet->kalemler->sum('miktar')),
                'cart_url' => UygulamaUrl::rota('cart.index', [], $request),
                'checkout_url' => UygulamaUrl::rota('checkout.index', [], $request),
                'mini_cart' => $this->miniSepetOzeti($sepet),
            ]);
        }

        return redirect()->back()->with([
            'success' => 'Urun sepete eklendi.',
            'cart_recently_added' => true,
        ]);
    }

    public function guncelle(Request $request, int $kalemId): RedirectResponse
    {
        $request->validate([
            'miktar' => ['required', 'numeric', 'min:1'],
        ]);

        $sepet = $this->sepetServisi->kalemMiktarGuncelle($request, $kalemId, (float) $request->input('miktar'));
        $this->sepetSayisiniOtumdaTazele($request, $sepet);

        return redirect()->to(UygulamaUrl::rota('cart.index', [], $request))->with('success', 'Sepet guncellendi.');
    }

    public function sil(Request $request, int $kalemId): RedirectResponse
    {
        $sepet = $this->sepetServisi->kalemSil($request, $kalemId);
        $this->sepetSayisiniOtumdaTazele($request, $sepet);

        return redirect()->to(UygulamaUrl::rota('cart.index', [], $request))->with('success', 'Urun sepetten kaldirildi.');
    }

    public function kuponUygula(Request $request): RedirectResponse
    {
        $request->validate([
            'kupon_kodu' => ['nullable', 'string', 'max:64'],
        ]);

        $this->sepetServisi->kuponKoduKaydet($request, (string) $request->input('kupon_kodu', ''));

        return redirect()->to(UygulamaUrl::rota('cart.index', [], $request))->with('success', 'Kupon kodu guncellendi.');
    }

    private function sepetSayisiniOtumdaTazele(Request $request, \App\Models\Ecommerce\Sepet $sepet): void
    {
        $request->session()->put('aktif_sepet_urun_adedi', (int) round((float) $sepet->kalemler->sum('miktar')));
    }

    /**
     * @return array{count:int,items:array<int,array{name:string,url:string,image_url:string,quantity_label:string,quantity_value:string,line_total:string,update_url:string,remove_url:string}>,more_count:int,subtotal:string,cart_url:string,checkout_url:string}
     */
    private function miniSepetOzeti(Sepet $sepet): array
    {
        $kalemler = $sepet->kalemler;
        $gosterilecekKalemler = $kalemler->take(10);
        $araToplam = $kalemler->sum(function ($kalem): float {
            $paraBirimi = strtoupper((string) ($kalem->para_birimi ?: 'TRY'));
            $kdvOrani = (float) ($kalem->kdv_orani ?? 0);
            $sonSatirToplami = round((float) $kalem->satir_toplami * (1 + ($kdvOrani / 100)), 2);

            return $this->frontFiyatServisi->cevir($sonSatirToplami, $paraBirimi);
        });

        return [
            'count' => (int) round((float) $kalemler->sum('miktar')),
            'items' => $gosterilecekKalemler->map(function ($kalem): array {
                $stok = $kalem->stokKarti;
                $gorselYolu = $stok?->og_gorsel;
                $gorselUrl = $stok?->kapak_gorsel_url
                    ?: ($gorselYolu
                        ? asset('uploads/'.ltrim(str_replace('\\', '/', (string) $gorselYolu), '/'))
                        : asset('theme/yalovakamera/images/yalova_kamera.png'));
                $paraBirimi = strtoupper((string) ($kalem->para_birimi ?: 'TRY'));
                $adet = (float) $kalem->miktar;
                $kdvOrani = (float) ($kalem->kdv_orani ?? 0);
                $sonSatirToplami = round((float) $kalem->satir_toplami * (1 + ($kdvOrani / 100)), 2);

                return [
                    'name' => (string) $kalem->urun_adi_snapshot,
                    'url' => $stok?->slug
                        ? UygulamaUrl::rota('products.show', ['slug' => $stok->slug])
                        : UygulamaUrl::rota('cart.index'),
                    'image_url' => $gorselUrl,
                    'quantity_label' => rtrim(rtrim(number_format($adet, 2, ',', '.'), '0'), ',').' adet',
                    'quantity_value' => rtrim(rtrim(number_format($adet, 2, '.', ''), '0'), '.'),
                    'line_total' => $this->frontFiyatServisi->cevirVeFormatla($sonSatirToplami, $paraBirimi),
                    'update_url' => UygulamaUrl::rota('cart.update', ['kalemId' => $kalem->id]),
                    'remove_url' => UygulamaUrl::rota('cart.remove', ['kalemId' => $kalem->id]),
                ];
            })->values()->all(),
            'more_count' => max(0, $kalemler->count() - $gosterilecekKalemler->count()),
            'subtotal' => $this->frontFiyatServisi->formatla((float) $araToplam),
            'cart_url' => UygulamaUrl::rota('cart.index'),
            'checkout_url' => UygulamaUrl::rota('checkout.index'),
        ];
    }
}
