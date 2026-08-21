<?php

namespace App\Http\Controllers;

use App\Models\Ecommerce\EcommerceKullaniciAdresi;
use App\Models\Ecommerce\EcommerceMesajKonu;
use App\Models\Ecommerce\Siparis;
use App\Models\Muhasebe\StokKarti;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Services\EcommerceCariServisi;
use App\Services\EcommerceMesajServisi;
use App\Services\EcommerceSiparisTalepServisi;
use App\Services\EcommerceUlkeServisi;
use App\Services\TenantContextService;
use App\Support\UygulamaUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HesabimController extends Controller
{
    public function __construct(
        private readonly TenantContextService $tenantContextService,
        private readonly EcommerceMesajServisi $ecommerceMesajServisi,
        private readonly EcommerceUlkeServisi $ulkeServisi,
        private readonly EcommerceSiparisTalepServisi $siparisTalepServisi,
        private readonly EcommerceCariServisi $ecommerceCariServisi,
    ) {}

    public function index(Request $request): View
    {
        $firmaId = (int) ($this->tenantContextService->aktifFirmaId() ?? 0);
        $kullaniciId = (int) $request->user()->id;

        $siparisAdedi = $this->odemeBasariliSiparisSorgusu($firmaId, $kullaniciId)->count();
        $acikMesajAdedi = EcommerceMesajKonu::query()
            ->where('firma_id', $firmaId)
            ->where('kullanici_id', $kullaniciId)
            ->where('durum', '!=', 'tamamlandi')
            ->count();

        return view('front.hesabim.index', [
            'siparisAdedi' => $siparisAdedi,
            'acikMesajAdedi' => $acikMesajAdedi,
        ]);
    }

    public function siparisler(Request $request): View
    {
        $firmaId = (int) ($this->tenantContextService->aktifFirmaId() ?? 0);
        $kullaniciId = (int) $request->user()->id;

        $siparisler = $this->odemeBasariliSiparisSorgusu($firmaId, $kullaniciId)
            ->latest('id')
            ->paginate(12);

        return view('front.hesabim.siparisler', ['siparisler' => $siparisler]);
    }

    public function profil(Request $request): View
    {
        return view('front.hesabim.profil', [
            'kullanici' => $request->user(),
        ]);
    }

    public function adresler(Request $request): View
    {
        $firmaId = (int) ($this->tenantContextService->aktifFirmaId() ?? 0);
        $kullaniciId = (int) $request->user()->id;

        $teslimatAdresleri = EcommerceKullaniciAdresi::query()
            ->where('firma_id', $firmaId)
            ->where('kullanici_id', $kullaniciId)
            ->where('adres_tipi', EcommerceKullaniciAdresi::TIP_TESLIMAT)
            ->orderByDesc('varsayilan_teslimat_mi')
            ->latest('id')
            ->get();
        $faturaCari = $this->ecommerceCariServisi->kullaniciIcinCariOlusturVeyaGuncelle($request->user(), $firmaId);
        $gecisFaturaAdresi = EcommerceKullaniciAdresi::query()
            ->where('firma_id', $firmaId)
            ->where('kullanici_id', $kullaniciId)
            ->where('adres_tipi', EcommerceKullaniciAdresi::TIP_FATURA)
            ->latest('id')
            ->first();

        if (
            $gecisFaturaAdresi
            && trim((string) ($faturaCari->adres ?? '')) === ''
            && trim((string) ($faturaCari->vergi_no ?? '')) === ''
            && trim((string) ($faturaCari->vergi_dairesi ?? '')) === ''
        ) {
            $adresMetni = trim(implode(' ', array_filter([
                (string) ($gecisFaturaAdresi->mahalle ?? ''),
                (string) ($gecisFaturaAdresi->acik_adres ?? ''),
                (string) ($gecisFaturaAdresi->posta_kodu ?? ''),
            ])));

            $faturaCari = $this->ecommerceCariServisi->kullaniciIcinCariOlusturVeyaGuncelle($request->user(), $firmaId, [
                'ad_soyad' => (string) ($gecisFaturaAdresi->ad_soyad ?: $request->user()->ad_soyad ?: $request->user()->name),
                'telefon' => (string) ($gecisFaturaAdresi->telefon ?: $request->user()->telefon),
                'email' => (string) ($request->user()->email ?? ''),
                'adres' => $adresMetni,
                'il' => (string) ($gecisFaturaAdresi->sehir ?? ''),
                'ilce' => (string) ($gecisFaturaAdresi->ilce ?? ''),
                'posta_kodu' => (string) ($gecisFaturaAdresi->posta_kodu ?? ''),
                'vergi_dairesi' => (string) ($gecisFaturaAdresi->vergi_dairesi ?? ''),
                'vergi_no' => (string) ($gecisFaturaAdresi->vergi_no ?? ''),
            ]);
        }

        return view('front.hesabim.adresler', [
            'teslimatAdresleri' => $teslimatAdresleri,
            'faturaCari' => $faturaCari,
            'ulkeSecenekleri' => $this->ulkeServisi->checkoutUlkeSecenekleri($firmaId),
        ]);
    }

    public function adresKaydet(Request $request): RedirectResponse
    {
        $firmaId = (int) ($this->tenantContextService->aktifFirmaId() ?? 0);
        $kullaniciId = (int) $request->user()->id;
        $data = $this->adresVerisiDogrula($request);
        $data['adres_tipi'] = EcommerceKullaniciAdresi::TIP_TESLIMAT;
        $data['varsayilan_fatura_mi'] = false;

        DB::transaction(function () use ($data, $firmaId, $kullaniciId, $request): void {
            $adres = new EcommerceKullaniciAdresi();
            $adres->fill($data);
            $adres->firma_id = $firmaId;
            $adres->kullanici_id = $kullaniciId;

            if ($adres->adres_tipi === EcommerceKullaniciAdresi::TIP_FATURA) {
                $faturaAdresiVarMi = EcommerceKullaniciAdresi::query()
                    ->where('firma_id', $firmaId)
                    ->where('kullanici_id', $kullaniciId)
                    ->where('adres_tipi', EcommerceKullaniciAdresi::TIP_FATURA)
                    ->exists();

                if ($faturaAdresiVarMi) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'adres_tipi' => 'Sadece bir fatura adresi ekleyebilirsiniz. Mevcut fatura adresinizi düzenleyin.',
                    ]);
                }

                $adres->varsayilan_teslimat_mi = false;
                $adres->varsayilan_fatura_mi = true;
            } else {
                $teslimatAdresiVarMi = EcommerceKullaniciAdresi::query()
                    ->where('firma_id', $firmaId)
                    ->where('kullanici_id', $kullaniciId)
                    ->where('adres_tipi', EcommerceKullaniciAdresi::TIP_TESLIMAT)
                    ->exists();

                if (! $teslimatAdresiVarMi) {
                    $adres->varsayilan_teslimat_mi = true;
                }

                $adres->varsayilan_fatura_mi = false;
            }

            $adres->save();

            $this->varsayilanAdresleriDengele($adres);
            $this->faturaAdresiniCariIleSenkronla($adres, $request);
        });

        return redirect()
            ->to(UygulamaUrl::rota('account.addresses'))
            ->with('success', 'Adres kaydedildi.');
    }

    public function adresGuncelle(Request $request, EcommerceKullaniciAdresi $adres): RedirectResponse
    {
        $this->adresErisimKontrol($request, $adres);
        $data = $this->adresVerisiDogrula($request);

        DB::transaction(function () use ($adres, $data, $request): void {
            $data['adres_tipi'] = (string) ($adres->adres_tipi ?: EcommerceKullaniciAdresi::TIP_TESLIMAT);
            $adres->fill($data);

            if ($adres->adres_tipi === EcommerceKullaniciAdresi::TIP_FATURA) {
                $adres->varsayilan_teslimat_mi = false;
                $adres->varsayilan_fatura_mi = true;
            } else {
                $adres->varsayilan_fatura_mi = false;
            }

            $adres->save();

            $this->varsayilanAdresleriDengele($adres);
            $this->faturaAdresiniCariIleSenkronla($adres, $request);
        });

        return redirect()
            ->to(UygulamaUrl::rota('account.addresses'))
            ->with('success', 'Adres güncellendi.');
    }

    public function adresSil(Request $request, EcommerceKullaniciAdresi $adres): RedirectResponse
    {
        $this->adresErisimKontrol($request, $adres);
        $faturaVarsayilaniSilindi = (bool) $adres->varsayilan_fatura_mi;
        $teslimatVarsayilaniSilindi = (bool) $adres->varsayilan_teslimat_mi;

        DB::transaction(function () use ($adres, $request, $faturaVarsayilaniSilindi, $teslimatVarsayilaniSilindi): void {
            $firmaId = (int) $adres->firma_id;
            $kullaniciId = (int) $adres->kullanici_id;
            $adres->delete();

            if ($teslimatVarsayilaniSilindi && $adres->adres_tipi === EcommerceKullaniciAdresi::TIP_TESLIMAT) {
                $yeniTeslimatAdresi = EcommerceKullaniciAdresi::query()
                    ->where('firma_id', $firmaId)
                    ->where('kullanici_id', $kullaniciId)
                    ->where('adres_tipi', EcommerceKullaniciAdresi::TIP_TESLIMAT)
                    ->latest('id')
                    ->first();

                if ($yeniTeslimatAdresi) {
                    $yeniTeslimatAdresi->forceFill(['varsayilan_teslimat_mi' => true])->save();
                }
            }

            if ($faturaVarsayilaniSilindi && $adres->adres_tipi === EcommerceKullaniciAdresi::TIP_FATURA) {
                $yeniFaturaAdresi = EcommerceKullaniciAdresi::query()
                    ->where('firma_id', $firmaId)
                    ->where('kullanici_id', $kullaniciId)
                    ->where('adres_tipi', EcommerceKullaniciAdresi::TIP_FATURA)
                    ->latest('id')
                    ->first();

                if ($yeniFaturaAdresi) {
                    $yeniFaturaAdresi->forceFill(['varsayilan_fatura_mi' => true])->save();
                    $this->faturaAdresiniCariIleSenkronla($yeniFaturaAdresi, $request);
                }
            }
        });

        return redirect()
            ->to(UygulamaUrl::rota('account.addresses'))
            ->with('success', 'Adres silindi.');
    }

    public function adresleriSil(Request $request): RedirectResponse
    {
        $firmaId = (int) ($this->tenantContextService->aktifFirmaId() ?? 0);
        $kullaniciId = (int) $request->user()->id;
        $ids = collect((array) $request->input('adres_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return redirect()
                ->to(UygulamaUrl::rota('account.addresses'))
                ->withErrors(['adres_ids' => 'Silmek için en az bir adres seçmelisiniz.']);
        }

        DB::transaction(function () use ($firmaId, $kullaniciId, $ids): void {
            $varsayilanSiliniyorMu = EcommerceKullaniciAdresi::query()
                ->where('firma_id', $firmaId)
                ->where('kullanici_id', $kullaniciId)
                ->where('adres_tipi', EcommerceKullaniciAdresi::TIP_TESLIMAT)
                ->where('varsayilan_teslimat_mi', true)
                ->whereIn('id', $ids)
                ->exists();

            EcommerceKullaniciAdresi::query()
                ->where('firma_id', $firmaId)
                ->where('kullanici_id', $kullaniciId)
                ->where('adres_tipi', EcommerceKullaniciAdresi::TIP_TESLIMAT)
                ->whereIn('id', $ids)
                ->delete();

            if ($varsayilanSiliniyorMu) {
                $yeniVarsayilan = EcommerceKullaniciAdresi::query()
                    ->where('firma_id', $firmaId)
                    ->where('kullanici_id', $kullaniciId)
                    ->where('adres_tipi', EcommerceKullaniciAdresi::TIP_TESLIMAT)
                    ->latest('id')
                    ->first();

                if ($yeniVarsayilan) {
                    $yeniVarsayilan->forceFill(['varsayilan_teslimat_mi' => true])->save();
                }
            }
        });

        return redirect()
            ->to(UygulamaUrl::rota('account.addresses'))
            ->with('success', 'Seçili adresler silindi.');
    }

    public function varsayilanTeslimatAdresiYap(Request $request, EcommerceKullaniciAdresi $adres): RedirectResponse
    {
        $this->adresErisimKontrol($request, $adres);
        abort_unless($adres->adres_tipi === EcommerceKullaniciAdresi::TIP_TESLIMAT, 404);

        DB::transaction(function () use ($adres): void {
            EcommerceKullaniciAdresi::query()
                ->where('firma_id', (int) $adres->firma_id)
                ->where('kullanici_id', (int) $adres->kullanici_id)
                ->where('adres_tipi', EcommerceKullaniciAdresi::TIP_TESLIMAT)
                ->update(['varsayilan_teslimat_mi' => false]);

            $adres->forceFill(['varsayilan_teslimat_mi' => true])->save();
        });

        return redirect()
            ->to(UygulamaUrl::rota('account.addresses'))
            ->with('success', 'Varsayılan teslimat adresi güncellendi.');
    }

    public function adresiFaturaBilgisiYap(Request $request, EcommerceKullaniciAdresi $adres): RedirectResponse
    {
        $this->adresErisimKontrol($request, $adres);
        abort_unless($adres->adres_tipi === EcommerceKullaniciAdresi::TIP_TESLIMAT, 404);

        $adresMetni = trim(implode(' ', array_filter([
            (string) ($adres->mahalle ?? ''),
            (string) ($adres->acik_adres ?? ''),
        ])));

        $this->ecommerceCariServisi->kullaniciIcinCariOlusturVeyaGuncelle($request->user(), (int) $adres->firma_id, [
            'ad_soyad' => (string) ($adres->ad_soyad ?: $request->user()->ad_soyad ?: $request->user()->name),
            'telefon' => (string) ($adres->telefon ?: $request->user()->telefon),
            'email' => (string) ($request->user()->email ?? ''),
            'adres' => $adresMetni,
            'il' => (string) ($adres->sehir ?? ''),
            'ilce' => (string) ($adres->ilce ?? ''),
            'posta_kodu' => (string) ($adres->posta_kodu ?? ''),
        ]);

        return redirect()
            ->to(UygulamaUrl::rota('account.addresses'))
            ->with('success', 'Seçili adres fatura bilgileriniz olarak ayarlandı.');
    }

    public function faturaAdresiGuncelle(Request $request): RedirectResponse
    {
        $firmaId = (int) ($this->tenantContextService->aktifFirmaId() ?? 0);
        $kullanici = $request->user();

        $data = $request->validate([
            'ad' => ['required', 'string', 'max:160'],
            'telefon' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:191'],
            'vergi_dairesi' => ['nullable', 'string', 'max:128'],
            'vergi_no' => ['nullable', 'string', 'max:32'],
            'il' => ['nullable', 'string', 'max:80'],
            'ilce' => ['nullable', 'string', 'max:80'],
            'posta_kodu' => ['nullable', 'string', 'max:20'],
            'adres' => ['nullable', 'string', 'max:1000'],
        ], [
            'ad.required' => 'Fatura unvanı zorunludur.',
            'email.email' => 'E-posta adresi geçerli değil.',
        ]);

        $this->ecommerceCariServisi->kullaniciIcinCariOlusturVeyaGuncelle($kullanici, $firmaId, [
            'ad' => trim((string) $data['ad']),
            'telefon' => trim((string) ($data['telefon'] ?? '')),
            'email' => trim((string) ($data['email'] ?? $kullanici->email ?? '')),
            'vergi_dairesi' => trim((string) ($data['vergi_dairesi'] ?? '')),
            'vergi_no' => trim((string) ($data['vergi_no'] ?? '')),
            'adres' => trim((string) ($data['adres'] ?? '')),
            'il' => trim((string) ($data['il'] ?? '')),
            'ilce' => trim((string) ($data['ilce'] ?? '')),
            'posta_kodu' => trim((string) ($data['posta_kodu'] ?? '')),
        ]);

        return redirect()
            ->to(UygulamaUrl::rota('account.addresses'))
            ->with('success', 'Fatura bilgileriniz güncellendi.');
    }

    public function profilGuncelle(Request $request): RedirectResponse
    {
        $kullanici = $request->user();

        $data = $request->validate([
            'ad_soyad' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($kullanici->id)],
            'telefon' => ['nullable', 'string', 'max:32'],
        ], [
            'ad_soyad.required' => 'Ad Soyad zorunludur.',
            'email.required' => 'E-posta zorunludur.',
            'email.unique' => 'Bu e-posta adresi başka bir hesapta kayıtlı.',
        ]);

        $firmaId = (int) ($this->tenantContextService->aktifFirmaId() ?? 0);
        $adSoyad = trim((string) $data['ad_soyad']);
        $email = mb_strtolower(trim((string) $data['email']), 'UTF-8');
        $telefon = trim((string) ($data['telefon'] ?? ''));

        DB::transaction(function () use ($kullanici, $firmaId, $adSoyad, $email, $telefon): void {
            $kullanici->ad_soyad = $adSoyad;
            $kullanici->name = $adSoyad;
            $kullanici->email = $email;

            if (Schema::hasColumn('users', 'telefon')) {
                $kullanici->telefon = $telefon;
            }

            $kullanici->save();

            if ($firmaId > 0) {
                $this->ecommerceCariServisi->kullaniciIcinCariOlusturVeyaGuncelle($kullanici->fresh() ?? $kullanici, $firmaId, [
                    'ad_soyad' => $adSoyad,
                    'telefon' => $telefon,
                    'email' => $email,
                ]);
            }
        });

        return redirect()
            ->to(UygulamaUrl::rota('account.profile'))
            ->with('success', 'Profil bilgileriniz güncellendi.');
    }

    public function siparisDetay(Request $request, Siparis $siparis): View
    {
        $this->siparisErisimKontrol($request, $siparis);

        return view('front.hesabim.siparis-detay', [
            'siparis' => $siparis->load(['kalemler.stokKarti.gorseller']),
        ]);
    }

    public function siparisTalep(Request $request, Siparis $siparis): RedirectResponse
    {
        $this->siparisErisimKontrol($request, $siparis);

        $data = $request->validate([
            'talep_turu' => ['required', Rule::in(['iptal', 'iade'])],
            'neden' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'talep_turu.required' => 'Talep türü zorunludur.',
            'talep_turu.in' => 'Talep türü geçerli değil.',
            'neden.required' => 'Talep nedeni zorunludur.',
            'neden.min' => 'Talep nedeni en az 10 karakter olmalıdır.',
        ]);

        $talepTuru = (string) $data['talep_turu'];
        $acilabilir = $talepTuru === 'iade'
            ? $this->siparisTalepServisi->iadeTalebiAcilabilirMi($siparis)
            : $this->siparisTalepServisi->iptalTalebiAcilabilirMi($siparis);

        if (! $acilabilir) {
            return back()->withErrors(['neden' => 'Bu sipariş durumu için talep oluşturulamaz.']);
        }

        $this->siparisTalepServisi->talepAc(
            $siparis,
            $talepTuru,
            (string) $data['neden'],
            (int) $request->user()->id
        );

        return redirect()
            ->to(UygulamaUrl::rota('account.orders.show', ['siparis' => $siparis->id]))
            ->with('success', $talepTuru === 'iade' ? 'İade talebiniz alındı.' : 'İptal talebiniz alındı.');
    }

    public function mesajlar(Request $request): View
    {
        $firmaId = (int) ($this->tenantContextService->aktifFirmaId() ?? 0);
        $kullaniciId = (int) $request->user()->id;

        $konular = EcommerceMesajKonu::query()
            ->where('firma_id', $firmaId)
            ->where('kullanici_id', $kullaniciId)
            ->with(['stokKarti', 'sonMesaj', 'siparis'])
            ->latest('updated_at')
            ->paginate(12);

        return view('front.hesabim.mesajlar', ['konular' => $konular]);
    }

    public function mesajYeniForm(Request $request): View
    {
        $firmaId = (int) ($this->tenantContextService->aktifFirmaId() ?? 0);
        $kullaniciId = (int) $request->user()->id;
        $seciliUrunId = $request->query('stok_karti_id');
        $urunler = StokKarti::query()
            ->where('firma_id', $firmaId)
            ->where('tur', StokKartiTuru::ETicaret->value)
            ->orderBy('ad')
            ->limit(200)
            ->get(['id', 'ad', 'slug']);
        $siparisler = Siparis::query()
            ->where('firma_id', $firmaId)
            ->where('kullanici_id', $kullaniciId)
            ->latest('id')
            ->limit(50)
            ->get(['id', 'siparis_no', 'created_at', 'genel_toplam', 'para_birimi']);

        return view('front.hesabim.mesaj-yeni', [
            'urunler' => $urunler,
            'siparisler' => $siparisler,
            'seciliUrunId' => $seciliUrunId,
            'seciliKonuTipi' => $request->query('konu_tipi'),
            'seciliBaslik' => $request->query('baslik'),
        ]);
    }

    public function mesajYeniKaydet(Request $request): RedirectResponse
    {
        $firmaId = (int) ($this->tenantContextService->aktifFirmaId() ?? 0);
        $kullanici = $request->user();
        $kullaniciId = (int) $kullanici->id;

        $data = $request->validate([
            'konu_tipi' => ['required', 'string', Rule::in(['musteri', 'urun'])],
            'stok_karti_id' => ['nullable', 'integer'],
            'siparis_id' => ['nullable', 'integer'],
            'baslik' => ['required', 'string', 'max:255'],
            'icerik' => ['required', 'string', 'max:4000'],
        ], [
            'konu_tipi.required' => 'Konu tipi zorunludur.',
            'baslik.required' => 'Konu başlığı zorunludur.',
            'icerik.required' => 'Mesaj zorunludur.',
        ]);

        $stokKartiId = isset($data['stok_karti_id']) ? (int) $data['stok_karti_id'] : null;
        if ((string) $data['konu_tipi'] === 'urun') {
            $urunVarMi = $stokKartiId
                ? StokKarti::query()
                    ->where('firma_id', $firmaId)
                    ->where('tur', StokKartiTuru::ETicaret->value)
                    ->whereKey($stokKartiId)
                    ->exists()
                : false;
            if (! $urunVarMi) {
                return back()
                    ->withErrors(['stok_karti_id' => 'Ürün mesajı için ürün seçmelisiniz.'])
                    ->withInput();
            }
        } else {
            $stokKartiId = null;
        }

        $siparisId = isset($data['siparis_id']) ? (int) $data['siparis_id'] : null;
        if ($siparisId) {
            $siparisVarMi = Siparis::query()
                ->where('firma_id', $firmaId)
                ->where('kullanici_id', $kullaniciId)
                ->whereKey($siparisId)
                ->exists();
            if (! $siparisVarMi) {
                return back()
                    ->withErrors(['siparis_id' => 'Seçilen sipariş bulunamadı.'])
                    ->withInput();
            }
        } else {
            $siparisId = null;
        }

        $konu = $this->ecommerceMesajServisi->konuOlustur([
            'firma_id' => $firmaId,
            'konu_tipi' => (string) $data['konu_tipi'],
            'kullanici_id' => $kullaniciId,
            'stok_karti_id' => $stokKartiId,
            'siparis_id' => $siparisId,
            'baslik' => (string) $data['baslik'],
            'musteri_ad_soyad' => (string) ($kullanici->ad_soyad ?: $kullanici->name),
            'musteri_email' => (string) ($kullanici->email ?? ''),
            'musteri_telefon' => (string) ($kullanici->telefon ?? ''),
            'ilk_mesaj' => (string) $data['icerik'],
            'gonderen_tipi' => 'musteri',
        ]);

        return redirect()
            ->to(UygulamaUrl::rota('account.messages.show', ['konu' => $konu->id]))
            ->with('success', 'Mesajınız oluşturuldu.');
    }

    public function mesajDetay(Request $request, EcommerceMesajKonu $konu): View
    {
        $this->konuErisimKontrol($request, $konu);

        return view('front.hesabim.mesaj-detay', [
            'konu' => $konu->load(['mesajlar', 'stokKarti', 'siparis']),
        ]);
    }

    public function mesajGonder(Request $request, EcommerceMesajKonu $konu): RedirectResponse
    {
        $this->konuErisimKontrol($request, $konu);

        $data = $request->validate([
            'icerik' => ['required', 'string', 'max:4000'],
        ]);

        $this->ecommerceMesajServisi->mesajiEkle(
            $konu,
            'musteri',
            (string) $data['icerik']
        );

        return redirect()
            ->to(UygulamaUrl::rota('account.messages.show', ['konu' => $konu->id]))
            ->with('success', 'Mesajınız gönderildi.');
    }

    private function siparisErisimKontrol(Request $request, Siparis $siparis): void
    {
        $firmaId = (int) ($this->tenantContextService->aktifFirmaId() ?? 0);
        $kullaniciId = (int) $request->user()->id;
        abort_unless((int) $siparis->firma_id === $firmaId && (int) $siparis->kullanici_id === $kullaniciId, 404);
    }

    private function konuErisimKontrol(Request $request, EcommerceMesajKonu $konu): void
    {
        $firmaId = (int) ($this->tenantContextService->aktifFirmaId() ?? 0);
        $kullaniciId = (int) $request->user()->id;
        abort_unless((int) $konu->firma_id === $firmaId && (int) $konu->kullanici_id === $kullaniciId, 404);
    }

    private function odemeBasariliSiparisSorgusu(int $firmaId, int $kullaniciId): Builder
    {
        return Siparis::query()
            ->where('firma_id', $firmaId)
            ->where('kullanici_id', $kullaniciId)
            ->whereIn('durum', Siparis::odemeAlindiDurumlari());
    }

    private function adresErisimKontrol(Request $request, EcommerceKullaniciAdresi $adres): void
    {
        $firmaId = (int) ($this->tenantContextService->aktifFirmaId() ?? 0);
        $kullaniciId = (int) $request->user()->id;
        abort_unless((int) $adres->firma_id === $firmaId && (int) $adres->kullanici_id === $kullaniciId, 404);
    }

    private function adresVerisiDogrula(Request $request): array
    {
        $data = $request->validate([
            'adres_tipi' => ['nullable', Rule::in([EcommerceKullaniciAdresi::TIP_TESLIMAT, EcommerceKullaniciAdresi::TIP_FATURA])],
            'baslik' => ['required', 'string', 'max:80'],
            'ad_soyad' => ['required', 'string', 'max:160'],
            'telefon' => ['required', 'string', 'max:32'],
            'vergi_dairesi' => ['nullable', 'string', 'max:128'],
            'vergi_no' => ['nullable', 'string', 'max:32'],
            'ulke_kodu' => ['required', 'string', 'max:10'],
            'sehir' => ['required', 'string', 'max:80'],
            'ilce' => ['required', 'string', 'max:80'],
            'mahalle' => ['nullable', 'string', 'max:120'],
            'posta_kodu' => ['nullable', 'string', 'max:20'],
            'acik_adres' => ['required', 'string', 'max:1000'],
            'adres_notu' => ['nullable', 'string', 'max:500'],
            'varsayilan_teslimat_mi' => ['nullable', 'boolean'],
            'varsayilan_fatura_mi' => ['nullable', 'boolean'],
        ], [
            'baslik.required' => 'Adres başlığı zorunludur.',
            'ad_soyad.required' => 'Ad Soyad zorunludur.',
            'telefon.required' => 'Telefon zorunludur.',
            'ulke_kodu.required' => 'Ülke seçimi zorunludur.',
            'sehir.required' => 'Şehir zorunludur.',
            'ilce.required' => 'İlçe zorunludur.',
            'acik_adres.required' => 'Açık adres zorunludur.',
        ]);

        if (! $this->ulkeServisi->postaKoduGecerliMi((string) ($data['ulke_kodu'] ?? 'TR'), $data['posta_kodu'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'posta_kodu' => 'Seçtiğiniz ülke için posta kodu formatı geçerli değil.',
            ]);
        }

        $data['adres_tipi'] = (string) ($data['adres_tipi'] ?? EcommerceKullaniciAdresi::TIP_TESLIMAT);
        $data['varsayilan_teslimat_mi'] = $data['adres_tipi'] === EcommerceKullaniciAdresi::TIP_TESLIMAT
            ? $request->boolean('varsayilan_teslimat_mi')
            : false;
        $data['varsayilan_fatura_mi'] = $data['adres_tipi'] === EcommerceKullaniciAdresi::TIP_FATURA;

        return $data;
    }

    private function varsayilanAdresleriDengele(EcommerceKullaniciAdresi $adres): void
    {
        if ($adres->adres_tipi === EcommerceKullaniciAdresi::TIP_TESLIMAT && $adres->varsayilan_teslimat_mi) {
            EcommerceKullaniciAdresi::query()
                ->where('firma_id', (int) $adres->firma_id)
                ->where('kullanici_id', (int) $adres->kullanici_id)
                ->where('adres_tipi', EcommerceKullaniciAdresi::TIP_TESLIMAT)
                ->where('id', '!=', (int) $adres->id)
                ->update(['varsayilan_teslimat_mi' => false]);
        }

        if ($adres->adres_tipi === EcommerceKullaniciAdresi::TIP_TESLIMAT) {
            $teslimatVarsayilaniVarMi = EcommerceKullaniciAdresi::query()
                ->where('firma_id', (int) $adres->firma_id)
                ->where('kullanici_id', (int) $adres->kullanici_id)
                ->where('adres_tipi', EcommerceKullaniciAdresi::TIP_TESLIMAT)
                ->where('varsayilan_teslimat_mi', true)
                ->exists();

            if (! $teslimatVarsayilaniVarMi) {
                $adres->forceFill(['varsayilan_teslimat_mi' => true])->save();
            }
        }

        if ($adres->adres_tipi === EcommerceKullaniciAdresi::TIP_FATURA) {
            $adres->forceFill([
                'varsayilan_teslimat_mi' => false,
                'varsayilan_fatura_mi' => true,
            ])->save();

            EcommerceKullaniciAdresi::query()
                ->where('firma_id', (int) $adres->firma_id)
                ->where('kullanici_id', (int) $adres->kullanici_id)
                ->where('adres_tipi', EcommerceKullaniciAdresi::TIP_FATURA)
                ->where('id', '!=', (int) $adres->id)
                ->delete();
        }

        $adres->refresh();
    }

    private function faturaAdresiniCariIleSenkronla(EcommerceKullaniciAdresi $adres, Request $request): void
    {
        if ($adres->adres_tipi !== EcommerceKullaniciAdresi::TIP_FATURA || ! (bool) $adres->varsayilan_fatura_mi) {
            return;
        }

        $kullanici = $request->user();
        if (! $kullanici) {
            return;
        }

        $adresMetni = trim(implode(' ', array_filter([
            (string) ($adres->mahalle ?? ''),
            (string) ($adres->acik_adres ?? ''),
            (string) ($adres->posta_kodu ?? ''),
        ])));

        $this->ecommerceCariServisi->kullaniciIcinCariOlusturVeyaGuncelle($kullanici, (int) $adres->firma_id, [
            'ad_soyad' => (string) ($adres->ad_soyad ?: $kullanici->ad_soyad ?: $kullanici->name),
            'telefon' => (string) ($adres->telefon ?: $kullanici->telefon),
            'email' => (string) ($kullanici->email ?? ''),
            'adres' => $adresMetni,
            'il' => (string) ($adres->sehir ?? ''),
            'ilce' => (string) ($adres->ilce ?? ''),
            'posta_kodu' => (string) ($adres->posta_kodu ?? ''),
            'vergi_dairesi' => (string) ($adres->vergi_dairesi ?? ''),
            'vergi_no' => (string) ($adres->vergi_no ?? ''),
        ]);
    }
}
