<?php

namespace App\Http\Controllers;

use App\Models\Ecommerce\EcommerceKullaniciAdresi;
use App\Models\Ecommerce\Siparis;
use App\Models\Muhasebe\StokKarti;
use App\Modules\Urun\Servisler\CheckoutServisi;
use App\Modules\Urun\Servisler\SepetServisi;
use App\Providers\Filament\AdminPanelProvider;
use App\Services\EcommerceFirmaAyarServisi;
use App\Services\EcommerceCheckoutOdemeYontemiServisi;
use App\Services\EcommerceKargoServisi;
use App\Services\EcommerceOdemeZamanAsimiFallbackServisi;
use App\Services\EcommerceUlkeServisi;
use App\Support\UygulamaUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly SepetServisi $sepetServisi,
        private readonly CheckoutServisi $checkoutServisi,
        private readonly EcommerceFirmaAyarServisi $ecommerceFirmaAyarServisi,
        private readonly EcommerceCheckoutOdemeYontemiServisi $ecommerceCheckoutOdemeYontemiServisi,
        private readonly EcommerceOdemeZamanAsimiFallbackServisi $zamanAsimiFallbackServisi,
        private readonly EcommerceKargoServisi $kargoServisi,
        private readonly EcommerceUlkeServisi $ulkeServisi,
    ) {}

    public function index(Request $request)
    {
        $sepet = $this->sepetServisi->sepetiGetirVeyaOlustur($request);
        if ($sepet->kalemler->isEmpty()) {
            return redirect()->to(UygulamaUrl::rota('cart.index', [], $request))->withErrors(['sepet' => 'Sepetiniz bos.']);
        }

        $kuponKodu = old('kupon_kodu', $this->sepetServisi->kuponKoduGetir($request));
        $toplamlar = $this->sepetServisi->toplamlar($sepet, is_string($kuponKodu) ? $kuponKodu : null, auth()->id());
        $firmaId = $this->firmaIdBul($sepet);
        $adresler = auth()->check() && $firmaId > 0
            ? EcommerceKullaniciAdresi::query()
                ->where('firma_id', $firmaId)
                ->where('kullanici_id', auth()->id())
                ->where('adres_tipi', EcommerceKullaniciAdresi::TIP_TESLIMAT)
                ->orderByDesc('varsayilan_teslimat_mi')
                ->latest('id')
                ->get()
            : collect();
        $ulkeSecenekleri = $firmaId > 0 ? $this->ulkeServisi->checkoutUlkeSecenekleri($firmaId) : $this->ulkeServisi->checkoutUlkeSecenekleri(0);
        $varsayilanUlke = $firmaId > 0 ? $this->ulkeServisi->varsayilanUlkeKodu($firmaId) : 'TR';
        $adres = [
            'teslimat_ulke' => old('teslimat_ulke', $varsayilanUlke),
            'teslimat_il' => old('teslimat_il', ''),
            'teslimat_posta_kodu' => old('teslimat_posta_kodu', ''),
        ];
        $odemeYontemleri = $firmaId > 0
            ? $this->ecommerceCheckoutOdemeYontemiServisi->secenekler(
                $firmaId,
                (float) ($toplamlar['genel_toplam'] ?? 0),
                (string) ($toplamlar['para_birimi'] ?? 'TRY')
            )
            : [];
        $varsayilanOdemeYontemi = collect($odemeYontemleri)->firstWhere('is_default', true) ?: ($odemeYontemleri[0] ?? null);

        return view('front.checkout.index', [
            'sepet' => $sepet,
            'kuponKodu' => $kuponKodu,
            'toplamlar' => $toplamlar,
            'adresler' => $adresler,
            'ulkeSecenekleri' => $ulkeSecenekleri,
            'varsayilanUlke' => $varsayilanUlke,
            'odemeYontemleri' => $odemeYontemleri,
            'seciliOdemeYontemi' => old('odeme_yontemi_secimi', $varsayilanOdemeYontemi['secim'] ?? null),
            'kargoSecenekleri' => $firmaId > 0 ? $this->kargoServisi->checkoutSecenekleri($firmaId, $sepet, $toplamlar, $adres) : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sepet = $this->sepetServisi->sepetiGetirVeyaOlustur($request);
        $kuponKodu = (string) $request->input('kupon_kodu', '');
        $this->sepetServisi->kuponKoduKaydet($request, $kuponKodu);
        $veri = $this->checkoutVerisiniHazirla($request, $sepet);

        try {
            $siparis = $this->checkoutServisi->siparisOlustur($sepet, $veri);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Checkout sipariş oluşturma hatası', [
                'user_id' => $request->user()?->id,
                'session_id' => $request->session()->getId(),
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'checkout' => 'Sipariş oluşturulurken beklenmeyen bir sorun oluştu. Lütfen sepetinizi, kargo seçiminizi ve ödeme yönteminizi kontrol edip tekrar deneyin. Sorun devam ederse bizimle iletişime geçin.',
                ])
                ->with('checkout_uyari', 'Siparişiniz kaydedilemedi; herhangi bir ödeme alınmadı. Bilgilerinizi kontrol ederek tekrar deneyebilirsiniz.');
        }

        $firmaId = is_numeric($siparis->firma_id) && (int) $siparis->firma_id > 0 ? (int) $siparis->firma_id : null;
        $this->zamanAsimiFallbackServisi->tetikle($firmaId);

        if ($firmaId !== null) {
            $ids = $this->ecommerceFirmaAyarServisi->tahsilatIds($firmaId);
            if ($ids['ayar_var_mi'] === false && (! $ids['cari_id'] || ! $ids['kasa_id'])) {
                $link = UygulamaUrl::uygulamaKoku($request).'/'.trim(AdminPanelProvider::adminPath(), '/').'/firma-ayarlari';
                $mesaj = 'E-ticaret tahsilat ayarlari eksik. Basarili odeme sonrasi finans kaydi yazilmayabilir. Firma ayarlarindan tahsilat cari/kasa secin: '.$link;
                $this->ecommerceFirmaAyarServisi->logEksikEcommerceAyarUyarisiThrottled($firmaId, $mesaj);
                $request->session()->flash('ecommerce_ayar_uyari', $mesaj);
            }
        }

        $request->session()->put('son_siparis_id', $siparis->id);
        $this->sepetServisi->kuponKoduTemizle($request);

        if ($siparis->durum === Siparis::DURUM_EFT_ONAYI_BEKLIYOR) {
            $this->sepetServisi->aktifSepetiBosaltVeOturumuTemizle($request);

            return redirect()
                ->to(UygulamaUrl::rota('odeme.show', ['siparis' => $siparis], $request))
                ->with('odeme', 'eft_talep');
        }

        return redirect()->to(UygulamaUrl::rota('odeme.show', ['siparis' => $siparis], $request));
    }

    public function kargoSecenekleri(Request $request): JsonResponse
    {
        $sepet = $this->sepetServisi->sepetiGetirVeyaOlustur($request);
        $kuponKodu = $this->sepetServisi->kuponKoduGetir($request);
        $toplamlar = $this->sepetServisi->toplamlar($sepet, is_string($kuponKodu) ? $kuponKodu : null, auth()->id());
        $firmaId = $this->firmaIdBul($sepet);

        if ($firmaId <= 0 || $sepet->kalemler->isEmpty()) {
            return response()->json([
                'options' => [],
                'base_total' => round((float) ($toplamlar['genel_toplam'] ?? 0), 2),
            ]);
        }

        $adres = [
            'teslimat_ulke' => $request->string('teslimat_ulke')->toString() ?: 'TR',
            'teslimat_il' => $request->string('teslimat_il')->toString(),
            'teslimat_posta_kodu' => $request->string('teslimat_posta_kodu')->toString(),
        ];

        $secenekler = $this->kargoServisi
            ->checkoutSecenekleri($firmaId, $sepet, $toplamlar, $adres)
            ->map(function (array $secenek): array {
                $yontem = $secenek['yontem'];

                return [
                    'id' => (int) $yontem->id,
                    'name' => (string) $yontem->ad,
                    'price' => round((float) $secenek['ucret'], 2),
                    'price_label' => (string) $secenek['ucret_formatli'],
                    'estimated_delivery' => (string) $secenek['tahmini_teslim'],
                    'supports_international' => (bool) $yontem->yurt_disi_aktif,
                    'scope_summary' => (string) ($secenek['kapsam_ozeti'] ?? ''),
                ];
            })
            ->values();

        return response()->json([
            'options' => $secenekler,
            'base_total' => round((float) ($toplamlar['genel_toplam'] ?? 0), 2),
        ]);
    }

    public function success(Request $request)
    {
        $siparisId = (int) $request->session()->get('son_siparis_id', 0);
        $siparis = $siparisId > 0 ? Siparis::query()->with('kalemler')->find($siparisId) : null;

        if ($siparis instanceof Siparis && Siparis::odemeAlindiDurumMu($siparis->durum)) {
            $this->sepetServisi->aktifSepetiBosaltVeOturumuTemizle($request);
        }

        return view('front.checkout.success', [
            'siparis' => $siparis,
        ]);
    }

    private function firmaIdBul($sepet): int
    {
        foreach ($sepet->kalemler as $kalem) {
            $stok = StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()
                ->whereKey($kalem->stok_karti_id)
                ->first(['id', 'firma_id']));
            $firmaId = (int) ($stok?->firma_id ?? 0);
            if ($firmaId > 0) {
                return $firmaId;
            }
        }

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutVerisiniHazirla(Request $request, $sepet): array
    {
        $veri = $request->all();
        $firmaId = $this->firmaIdBul($sepet);
        $kullanici = $request->user();

        if (! $kullanici || $firmaId <= 0) {
            return $veri;
        }

        $seciliAdresId = (int) $request->input('selected_address_id', 0);
        if ($seciliAdresId > 0) {
            $adres = EcommerceKullaniciAdresi::query()
                ->where('firma_id', $firmaId)
                ->where('kullanici_id', (int) $kullanici->id)
                ->where('adres_tipi', EcommerceKullaniciAdresi::TIP_TESLIMAT)
                ->whereKey($seciliAdresId)
                ->first();

            if ($adres) {
                return array_merge($veri, $this->adresCheckoutPayload($adres, $veri));
            }
        }

        if ($request->boolean('adresi_kaydet')) {
            $adres = EcommerceKullaniciAdresi::query()->create([
                'firma_id' => $firmaId,
                'kullanici_id' => (int) $kullanici->id,
                'adres_tipi' => EcommerceKullaniciAdresi::TIP_TESLIMAT,
                'baslik' => trim((string) $request->input('adres_baslik', 'Teslimat Adresi')) ?: 'Teslimat Adresi',
                'ad_soyad' => trim((string) $request->input('musteri_ad_soyad')),
                'telefon' => trim((string) $request->input('musteri_telefon')),
                'ulke_kodu' => strtoupper(mb_substr(trim((string) $request->input('teslimat_ulke', 'TR')), 0, 2, 'UTF-8')) ?: 'TR',
                'sehir' => trim((string) $request->input('teslimat_il')),
                'ilce' => trim((string) $request->input('teslimat_ilce')),
                'mahalle' => null,
                'posta_kodu' => trim((string) $request->input('teslimat_posta_kodu')) ?: null,
                'acik_adres' => trim((string) $request->input('teslimat_adresi')),
                'adres_notu' => trim((string) $request->input('notlar')) ?: null,
                'varsayilan_teslimat_mi' => $request->boolean('varsayilan_teslimat_mi'),
                'varsayilan_fatura_mi' => false,
            ]);

            if ($adres->varsayilan_teslimat_mi) {
                EcommerceKullaniciAdresi::query()
                    ->where('firma_id', $firmaId)
                    ->where('kullanici_id', (int) $kullanici->id)
                    ->whereKeyNot($adres->id)
                    ->update(['varsayilan_teslimat_mi' => false]);
            }
        }

        return $veri;
    }

    /**
     * @param  array<string, mixed>  $mevcut
     * @return array<string, mixed>
     */
    private function adresCheckoutPayload(EcommerceKullaniciAdresi $adres, array $mevcut): array
    {
        $adresSatirlari = array_filter([
            $adres->mahalle,
            $adres->acik_adres,
            $adres->adres_notu,
        ]);

        return [
            'musteri_ad_soyad' => trim((string) ($mevcut['musteri_ad_soyad'] ?? '')) ?: $adres->ad_soyad,
            'musteri_telefon' => trim((string) ($mevcut['musteri_telefon'] ?? '')) ?: $adres->telefon,
            'teslimat_ulke' => $adres->ulke_kodu ?: 'TR',
            'teslimat_il' => $adres->sehir,
            'teslimat_ilce' => $adres->ilce,
            'teslimat_posta_kodu' => $adres->posta_kodu,
            'teslimat_adresi' => implode("\n", $adresSatirlari),
        ];
    }
}
