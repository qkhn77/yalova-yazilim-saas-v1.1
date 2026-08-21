<?php

namespace App\Http\Controllers;

use App\Models\Ecommerce\Siparis;
use App\Models\Ecommerce\SiparisGecmisi;
use App\Modules\Odeme\OdemeProviderFactory;
use App\Modules\Urun\Servisler\SepetServisi;
use App\Modules\Urun\Servisler\SiparisGecmisServisi;
use App\Modules\Urun\Servisler\SiparisOdemeServisi;
use App\Services\EcommerceOdemeFirmaAyarServisi;
use App\Services\EcommerceOdemeZamanAsimiFallbackServisi;
use App\Support\UygulamaUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class OdemeController extends Controller
{
    public function __construct(
        private readonly SiparisOdemeServisi $siparisOdemeServisi,
        private readonly EcommerceOdemeZamanAsimiFallbackServisi $zamanAsimiFallbackServisi,
        private readonly EcommerceOdemeFirmaAyarServisi $ecommerceOdemeFirmaAyarServisi,
        private readonly OdemeProviderFactory $odemeProviderFactory,
        private readonly SiparisGecmisServisi $siparisGecmisServisi,
        private readonly SepetServisi $sepetServisi,
    ) {}

    public function show(Request $request, Siparis $siparis)
    {
        $this->sipariseErisimIzni($request, $siparis);

        $firmaId = is_numeric($siparis->firma_id) && (int) $siparis->firma_id > 0 ? (int) $siparis->firma_id : null;
        $this->zamanAsimiFallbackServisi->tetikle($firmaId);

        $this->siparisOdemeServisi->siparisZamanAsimindaIptal($siparis);

        $siparis = $siparis->fresh(['kalemler', 'odemeler']);
        if (! $siparis instanceof Siparis) {
            abort(404);
        }

        if ($siparis->durum === Siparis::DURUM_EFT_ONAYI_BEKLIYOR) {
            return view('front.odeme.show', [
                'siparis' => $siparis,
            ]);
        }

        // Provider aktifse gerçek ödeme akışını başlat.
        if ($firmaId !== null && Siparis::odemeAkisindaDurumMu($siparis->durum)) {
            $ayarlar = null;
            try {
                $ayarlar = $this->ecommerceOdemeFirmaAyarServisi->kontrolOdemeBaslatmaAyarlarVeyaNull($firmaId);
            } catch (ValidationException $e) {
                $msg = (string) ($e->errors()['odeme'][0] ?? 'Ödeme ayarı eksik.');
                $request->session()->flash('ecommerce_ayar_uyari', $msg);
            }

            if (is_array($ayarlar) && ! empty($ayarlar['ecommerce_odeme_provider']) && (bool) ($ayarlar['ecommerce_odeme_aktif_mi'] ?? false)) {
                $provider = (string) $ayarlar['ecommerce_odeme_provider'];
                $testModu = (bool) ($ayarlar['test_modu'] ?? false);

                try {
                    $ayarlar['user_ip'] = $request->ip();
                    $this->odemeBaslatildiGecmisiKaydet($siparis, $provider);
                    $providerService = $this->odemeProviderFactory->make($provider);
                    $start = $providerService->odemeBaslat($siparis, $ayarlar);

                    if (($start['mode'] ?? '') === 'paytr_iframe') {
                        return view('front.odeme.provider.paytr-iframe', [
                            'siparis' => $siparis,
                            'iframe_src' => (string) ($start['iframe_src'] ?? ''),
                        ]);
                    }

                    if (($start['mode'] ?? '') === 'redirect') {
                        return redirect()->away((string) ($start['url'] ?? UygulamaUrl::rota('odeme.show', ['siparis' => $siparis], $request)));
                    }

                    if (($start['mode'] ?? '') === 'iyzico_checkout_form') {
                        return view('front.odeme.provider.iyzico-checkout-form', [
                            'siparis' => $siparis,
                            'checkout_form_content' => (string) ($start['checkout_form_content'] ?? ''),
                        ]);
                    }
                } catch (ValidationException $e) {
                    $msg = (string) ($e->errors()['odeme'][0] ?? 'Ödeme başlatılamadı.');
                    if ($testModu) {
                        $request->session()->flash(
                            'ecommerce_testmodu_bilgi',
                            'iyzico test başlatılamadı. Test modu nedeniyle simülasyon ekranı açıldı. Detay: '.$msg
                        );
                    } else {
                        $request->session()->flash('ecommerce_ayar_uyari', $msg);
                    }
                }
            }
        }

        // Varsayılan: mock/ekran geliştirme akışı.
        return view('front.odeme.show', [
            'siparis' => $siparis,
        ]);
    }

    public function basarili(Request $request, Siparis $siparis): RedirectResponse
    {
        $this->sipariseErisimIzni($request, $siparis);

        $this->siparisOdemeServisi->mockOdemeBasarili($siparis);
        $this->sepetServisi->aktifSepetiBosaltVeOturumuTemizle($request);

        $request->session()->put('son_siparis_id', $siparis->id);

        return redirect()
            ->to(UygulamaUrl::rota('checkout.success', [], $request))
            ->with('odeme', 'ok');
    }

    public function basarisiz(Request $request, Siparis $siparis): RedirectResponse
    {
        $this->sipariseErisimIzni($request, $siparis);

        $this->siparisOdemeServisi->mockOdemeBasarisiz($siparis);

        return redirect()
            ->to(UygulamaUrl::rota('odeme.show', ['siparis' => $siparis], $request))
            ->with('odeme', 'fail_retry');
    }

    public function tekrarDene(Request $request, Siparis $siparis): RedirectResponse
    {
        $this->sipariseErisimIzni($request, $siparis);

        $firmaId = is_numeric($siparis->firma_id) && (int) $siparis->firma_id > 0 ? (int) $siparis->firma_id : null;
        $ayarlar = null;
        if ($firmaId !== null) {
            try {
                $ayarlar = $this->ecommerceOdemeFirmaAyarServisi->kontrolOdemeBaslatmaAyarlarVeyaNull($firmaId);
            } catch (ValidationException) {
                $ayarlar = null;
            }
        }

        if (is_array($ayarlar) && ! empty($ayarlar['ecommerce_odeme_provider']) && (bool) ($ayarlar['ecommerce_odeme_aktif_mi'] ?? false)) {
            $provider = (string) $ayarlar['ecommerce_odeme_provider'];
            $this->siparisOdemeServisi->providerYeniOdemeDenemesiBaslat($siparis, $provider);
        } else {
            $this->siparisOdemeServisi->yeniOdemeDenemesiBaslat($siparis);
        }

        return redirect()
            ->to(UygulamaUrl::rota('odeme.show', ['siparis' => $siparis], $request))
            ->with('odeme', 'yeni_deneme');
    }

    private function sipariseErisimIzni(Request $request, Siparis $siparis): void
    {
        $kullaniciId = Auth::id();
        if ($kullaniciId !== null && (int) $siparis->kullanici_id === (int) $kullaniciId) {
            return;
        }

        if ((int) $request->session()->get('son_siparis_id', 0) === (int) $siparis->id) {
            return;
        }

        abort(403);
    }

    private function odemeBaslatildiGecmisiKaydet(Siparis $siparis, string $provider): void
    {
        $sonBaslatma = $siparis->gecmisleri()
            ->where('olay', SiparisGecmisi::OLAY_ODEME_BASLATILDI)
            ->latest('id')
            ->first();

        if ($sonBaslatma !== null && $sonBaslatma->created_at !== null && $sonBaslatma->created_at->gt(now()->subMinutes(2))) {
            return;
        }

        $this->siparisGecmisServisi->kaydet(
            $siparis,
            SiparisGecmisi::OLAY_ODEME_BASLATILDI,
            'Ödeme süreci başlatıldı',
            ['provider' => $provider],
        );
    }
}
