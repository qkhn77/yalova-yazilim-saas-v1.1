<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\HandlesLoginRecaptcha;
use App\Http\Controllers\Controller;
use App\Models\Firma;
use App\Models\FirmaKullanici;
use App\Models\User;
use App\Services\EcommerceFirmaAyarServisi;
use App\Services\EcommerceCariServisi;
use App\Services\ModulErisimService;
use App\Services\SistemOlayServisi;
use App\Services\TenantContextService;
use App\Support\KullaniciTablosuYardimcisi;
use App\Support\PanelYonlendirme;
use App\Support\UygulamaUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TenantAuthController extends Controller
{
    use HandlesLoginRecaptcha;

    private const SESSION_UYE_OTURUMU = 'uye_oturumu';

    public function girisFormu(Request $request, TenantContextService $tenantBaglam): Response|RedirectResponse
    {
        if (Auth::check() && $tenantBaglam->hasAktifFirma()) {
            return PanelYonlendirme::guvenliIntendedIlePanel($request);
        }

        return response()
            ->view('auth.tenant-login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function kayitFormu(Request $request): View
    {
        return view('auth.tenant-register');
    }

    public function kayit(
        Request $request,
        TenantContextService $tenantBaglam,
        ModulErisimService $modulErisimService,
        EcommerceFirmaAyarServisi $ecommerceFirmaAyarServisi,
        EcommerceCariServisi $ecommerceCariServisi,
    ): RedirectResponse
    {
        $dogrulanmis = $request->validate([
            'ad_soyad' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:191'],
            'telefon_ulke_kodu' => ['nullable', 'string', 'max:10'],
            'telefon' => ['required', 'string', 'max:32'],
            'sifre' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'ad_soyad.required' => 'Ad Soyad zorunludur.',
            'email.required' => 'E-posta zorunludur.',
            'telefon.required' => 'Telefon numarası zorunludur.',
            'sifre.required' => 'Şifre zorunludur.',
            'sifre.min' => 'Şifre en az 8 karakter olmalıdır.',
            'sifre.confirmed' => 'Şifre tekrarı eşleşmiyor.',
        ]);

        $firma = $this->kayitIcinFirmaBul($modulErisimService, $ecommerceFirmaAyarServisi);

        if (! $firma) {
            return back()->withErrors([
                'email' => 'Kayıt için uygun aktif e-ticaret firması bulunamadı.',
            ])->withInput($request->except('sifre', 'sifre_confirmation'));
        }

        $email = mb_strtolower(trim((string) $dogrulanmis['email']));
        $adSoyad = trim((string) $dogrulanmis['ad_soyad']);
        $telefonUlkeKodu = trim((string) ($dogrulanmis['telefon_ulke_kodu'] ?? '+90'));
        if ($telefonUlkeKodu === '') {
            $telefonUlkeKodu = '+90';
        }

        $telefon = $this->telefonuBirlestir($telefonUlkeKodu, trim((string) $dogrulanmis['telefon']));
        $telefonKolonuVarMi = Schema::hasColumn((new User)->getTable(), 'telefon');
        $firmaKullaniciOnayDurumuKolonuVarMi = Schema::hasColumn('firma_kullanicilari', 'onay_durumu');

        $kullanici = DB::transaction(function () use ($email, $adSoyad, $telefon, $telefonKolonuVarMi, $firmaKullaniciOnayDurumuKolonuVarMi, $dogrulanmis, $firma, $ecommerceCariServisi): User {
            $kullanici = User::query()
                ->withoutGlobalScopes()
                ->where('email', $email)
                ->first();

            if (! $kullanici) {
                $yeniKullaniciVerisi = [
                    'name' => $adSoyad,
                    'ad_soyad' => $adSoyad,
                    'email' => $email,
                    'password' => (string) $dogrulanmis['sifre'],
                    'super_admin_mi' => false,
                ];

                if ($telefonKolonuVarMi) {
                    $yeniKullaniciVerisi['telefon'] = $telefon;
                }

                $kullanici = User::query()->create($yeniKullaniciVerisi);
            } elseif ($telefonKolonuVarMi && trim((string) ($kullanici->telefon ?? '')) === '') {
                $kullanici->telefon = $telefon;
                $kullanici->save();
            }

            $eslesen = FirmaKullanici::query()
                ->withoutGlobalScopes()
                ->where('firma_id', (int) $firma->id)
                ->where('kullanici_id', (int) $kullanici->id)
                ->first();

            if ($eslesen && $eslesen->deleted_at === null && (string) $eslesen->durum === 'aktif') {
                return $kullanici;
            }

            if ($eslesen && $eslesen->deleted_at !== null) {
                $eslesen->restore();
            }

            $kayit = $eslesen ?: new FirmaKullanici();
            $kayit->firma_id = (int) $firma->id;
            $kayit->kullanici_id = (int) $kullanici->id;
            $kayit->durum = 'aktif';
            if ($firmaKullaniciOnayDurumuKolonuVarMi) {
                $kayit->onay_durumu = 'aktif';
            }
            $kayit->varsayilan_firma_mi = true;
            $kayit->save();

            $ecommerceCariServisi->kullaniciIcinCariOlusturVeyaGuncelle($kullanici, (int) $firma->id, [
                'ad' => $adSoyad,
                'telefon' => $telefon,
                'email' => $email,
            ]);

            return $kullanici;
        });

        Auth::login($kullanici, true);
        $request->session()->regenerate();
        $this->uyeOturumuAyarla($request, $request->routeIs('buyer.register.attempt'));

        $firmaKullanici = FirmaKullanici::query()
            ->withoutGlobalScopes()
            ->where('firma_id', (int) $firma->id)
            ->where('kullanici_id', (int) $kullanici->id)
            ->whereNull('deleted_at')
            ->first();

        $tenantBaglam->firmaAyarla(
            $firma,
            $firmaKullanici?->rol_id ? (int) $firmaKullanici->rol_id : null,
            $firmaKullanici ? (int) $firmaKullanici->id : null
        );

        return redirect()
            ->to(UygulamaUrl::rota('account.index', [], $request))
            ->with('success', 'Üyelik kaydınız tamamlandı.');
    }

    private function kayitIcinFirmaBul(
        ModulErisimService $modulErisimService,
        EcommerceFirmaAyarServisi $ecommerceFirmaAyarServisi
    ): ?Firma {
        $aktifFirmalar = Firma::query()
            ->where('durum', 'aktif')
            ->orderBy('id')
            ->get();

        foreach ($aktifFirmalar as $firma) {
            $firmaId = (int) $firma->id;
            if (
                $modulErisimService->modulErisilebilirMi($firmaId, 'e_ticaret')
                && $ecommerceFirmaAyarServisi->firmaEtkinMi($firmaId, false)
            ) {
                return $firma;
            }
        }

        return $aktifFirmalar->first();
    }

    public function giris(Request $request, TenantContextService $tenantBaglam): RedirectResponse
    {
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        [$rules, $messages] = $this->recaptchaKurallariniEkle([
            'firma_kodu' => ['required', 'string', 'max:100'],
            'kullanici_adi_veya_eposta' => ['required', 'string', 'max:255'],
            'sifre' => ['required', 'string', 'max:255'],
        ], [
            'firma_kodu.required' => 'Firma kodu zorunludur.',
            'kullanici_adi_veya_eposta.required' => 'Kullanici adi veya e-posta zorunludur.',
            'sifre.required' => 'Sifre zorunludur.',
        ]);

        $dogrulanmis = $request->validate($rules, $messages);

        $bruteResult = $this->bruteForceKontrolEt(
            $request,
            (string) $dogrulanmis['kullanici_adi_veya_eposta'],
            (string) $dogrulanmis['firma_kodu']
        );
        if ($bruteResult !== null) {
            return $bruteResult;
        }

        $recaptchaResult = $this->recaptchaDogrulamasiniKontrolEt($request);
        if ($recaptchaResult !== null) {
            return $recaptchaResult;
        }

        $firma = Firma::query()
            ->where('firma_kodu', (string) $dogrulanmis['firma_kodu'])
            ->first();

        if (! $firma || $firma->durum !== 'aktif') {
            $this->basarisizDenemeKaydet(
                $request,
                (string) $dogrulanmis['kullanici_adi_veya_eposta'],
                (string) $dogrulanmis['firma_kodu']
            );

            return back()->withErrors([
                'firma_kodu' => 'Firma kodu gecersiz veya firma aktif degil.',
            ])->withInput($request->except('sifre'));
        }

        $girisKimlik = trim((string) $dogrulanmis['kullanici_adi_veya_eposta']);
        $kullanici = $this->firmaIcinKullaniciBul($firma, $girisKimlik);

        if (! $kullanici || ! Hash::check($dogrulanmis['sifre'], $kullanici->password)) {
            $this->basarisizDenemeKaydet(
                $request,
                (string) $dogrulanmis['kullanici_adi_veya_eposta'],
                (string) $dogrulanmis['firma_kodu']
            );

            return back()->withErrors([
                'kullanici_adi_veya_eposta' => 'Kullanici bilgisi veya sifre hatali.',
            ])->withInput($request->except('sifre'));
        }

        $yoneticiHesapMi = (bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false);
        if ($yoneticiHesapMi) {
            $this->basarisizDenemeKaydet(
                $request,
                (string) $dogrulanmis['kullanici_adi_veya_eposta'],
                (string) $dogrulanmis['firma_kodu']
            );

            return back()->withErrors([
                'firma_kodu' => 'Sistem yonetici hesaplari firma girisi kullanamaz. Lutfen yonetici giris sayfasini kullanin: '.UygulamaUrl::rota('yonetici.login', [], $request),
            ])->withInput($request->except('sifre'));
        }

        $firmaKullanici = FirmaKullanici::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firma->id)
            ->where('kullanici_id', $kullanici->id)
            ->where('durum', 'aktif')
            ->whereNull('deleted_at')
            ->first();

        if (! $firmaKullanici) {
            $this->basarisizDenemeKaydet(
                $request,
                (string) $dogrulanmis['kullanici_adi_veya_eposta'],
                (string) $dogrulanmis['firma_kodu']
            );

            return back()->withErrors([
                'firma' => 'Bu kullanici secilen firmada aktif degil.',
            ])->withInput($request->except('sifre'));
        }

        if (! $firmaKullanici->rol_id) {
            $this->basarisizDenemeKaydet(
                $request,
                (string) $dogrulanmis['kullanici_adi_veya_eposta'],
                (string) $dogrulanmis['firma_kodu']
            );

            return back()->withErrors([
                'kullanici_adi_veya_eposta' => 'Bu hesap üye hesabıdır. Lütfen üye girişi ekranını kullanın: '.UygulamaUrl::rota('buyer.login', [], $request),
            ])->withInput($request->except('sifre'));
        }

        if (isset($firmaKullanici->onay_durumu)
            && $firmaKullanici->onay_durumu !== null
            && (string) $firmaKullanici->onay_durumu !== 'aktif') {
            $this->basarisizDenemeKaydet(
                $request,
                (string) $dogrulanmis['kullanici_adi_veya_eposta'],
                (string) $dogrulanmis['firma_kodu']
            );

            return back()->withErrors([
                'firma' => 'Hesabiniz henuz onaylanmamis.',
            ])->withInput($request->except('sifre'));
        }

        $this->bruteForceTemizle(
            $request,
            (string) $dogrulanmis['kullanici_adi_veya_eposta'],
            (string) $dogrulanmis['firma_kodu']
        );

        Auth::login($kullanici, $request->boolean('beni_hatirla'));
        $request->session()->regenerate();
        $this->uyeOturumuAyarla($request, false);

        $tenantBaglam->firmaAyarla(
            $firma,
            $firmaKullanici->rol_id ? (int) $firmaKullanici->rol_id : null,
            (int) $firmaKullanici->id
        );

        return redirect()->to(PanelYonlendirme::anaSayfaUrl($request));
    }

    public function aliciGirisFormu(): View
    {
        return view('auth.alici-login');
    }

    public function aliciKayitFormu(): View
    {
        return view('auth.alici-register');
    }

    public function aliciGiris(Request $request, TenantContextService $tenantBaglam): RedirectResponse
    {
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        [$rules, $messages] = $this->recaptchaKurallariniEkle([
            'kullanici_adi_veya_eposta' => ['required', 'string', 'max:255'],
            'sifre' => ['required', 'string', 'max:255'],
        ], [
            'kullanici_adi_veya_eposta.required' => 'Kullanici adi veya e-posta zorunludur.',
            'sifre.required' => 'Sifre zorunludur.',
        ]);

        $dogrulanmis = $request->validate($rules, $messages);

        $kimlik = (string) $dogrulanmis['kullanici_adi_veya_eposta'];
        $bruteResult = $this->bruteForceKontrolEt($request, $kimlik, null);
        if ($bruteResult !== null) {
            return $bruteResult;
        }

        $recaptchaResult = $this->recaptchaDogrulamasiniKontrolEt($request);
        if ($recaptchaResult !== null) {
            return $recaptchaResult;
        }

        $kullanici = $this->kullaniciBul(trim($kimlik));
        if (! $kullanici || ! Hash::check((string) $dogrulanmis['sifre'], $kullanici->password)) {
            $this->basarisizDenemeKaydet($request, $kimlik, null);

            return back()->withErrors([
                'kullanici_adi_veya_eposta' => 'Kullanici bilgisi veya sifre hatali.',
            ])->withInput($request->except('sifre'));
        }

        $yoneticiHesapMi = (bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false);
        if ($yoneticiHesapMi) {
            $this->basarisizDenemeKaydet($request, $kimlik, null);

            return back()->withErrors([
                'kullanici_adi_veya_eposta' => 'Sistem yonetici hesaplari bu ekrani kullanamaz.',
            ])->withInput($request->except('sifre'));
        }

        $firmaKullanici = FirmaKullanici::query()
            ->withoutGlobalScopes()
            ->where('kullanici_id', (int) $kullanici->id)
            ->where('durum', 'aktif')
            ->whereNull('deleted_at')
            ->whereHas('firma', fn ($q) => $q->where('durum', 'aktif'))
            ->orderByDesc('varsayilan_firma_mi')
            ->orderBy('id')
            ->first();

        if (! $firmaKullanici || ! $firmaKullanici->firma) {
            $this->basarisizDenemeKaydet($request, $kimlik, null);

            return back()->withErrors([
                'firma' => 'Aktif alışveriş hesabı bulunamadı.',
            ])->withInput($request->except('sifre'));
        }

        if (isset($firmaKullanici->onay_durumu)
            && $firmaKullanici->onay_durumu !== null
            && (string) $firmaKullanici->onay_durumu !== 'aktif') {
            $this->basarisizDenemeKaydet($request, $kimlik, null);

            return back()->withErrors([
                'firma' => 'Hesabiniz henuz onaylanmamis.',
            ])->withInput($request->except('sifre'));
        }

        $this->bruteForceTemizle($request, $kimlik, null);
        Auth::login($kullanici, $request->boolean('beni_hatirla'));
        $request->session()->regenerate();
        $this->uyeOturumuAyarla($request, true);

        $tenantBaglam->firmaAyarla(
            $firmaKullanici->firma,
            $firmaKullanici->rol_id ? (int) $firmaKullanici->rol_id : null,
            (int) $firmaKullanici->id
        );

        return redirect()->to(UygulamaUrl::rota('account.index', [], $request));
    }

    private function kullaniciBul(string $girisKimlik): ?User
    {
        $tablo = (new User)->getTable();

        $sorgu = User::query()->withoutGlobalScopes();
        KullaniciTablosuYardimcisi::kullaniciSilinmemisFiltresiUygula($sorgu, $tablo);

        return $sorgu
            ->where($tablo.'.aktif_mi', true)
            ->where(function ($sorgu) use ($girisKimlik): void {
                $sorgu->where('kullanici_adi', $girisKimlik)
                    ->orWhere('email', $girisKimlik);
            })
            ->whereExists(function ($sorgu) use ($tablo): void {
                $sorgu->selectRaw('1')
                    ->from('firma_kullanicilari')
                    ->whereColumn('firma_kullanicilari.kullanici_id', $tablo.'.id')
                    ->where('firma_kullanicilari.durum', 'aktif')
                    ->whereNull('firma_kullanicilari.deleted_at');
            })
            ->first();
    }

    private function firmaIcinKullaniciBul(Firma $firma, string $girisKimlik): ?User
    {
        $tablo = (new User)->getTable();

        $sorgu = User::query()->withoutGlobalScopes();
        KullaniciTablosuYardimcisi::kullaniciSilinmemisFiltresiUygula($sorgu, $tablo);

        return $sorgu
            ->where($tablo.'.aktif_mi', true)
            ->where(function ($sorgu) use ($girisKimlik): void {
                $sorgu->where('kullanici_adi', $girisKimlik)
                    ->orWhere('email', $girisKimlik);
            })
            ->whereExists(function ($sorgu) use ($firma, $tablo): void {
                $sorgu->selectRaw('1')
                    ->from('firma_kullanicilari')
                    ->whereColumn('firma_kullanicilari.kullanici_id', $tablo.'.id')
                    ->where('firma_kullanicilari.firma_id', (int) $firma->id)
                    ->where('firma_kullanicilari.durum', 'aktif')
                    ->whereNull('firma_kullanicilari.deleted_at');
            })
            ->first();
    }

    public function cikis(Request $request): RedirectResponse
    {
        $hedefUrl = $this->cikisSonrasiHedefUrl($request);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to($hedefUrl);
    }

    private function cikisSonrasiHedefUrl(Request $request): string
    {
        $anaSayfa = UygulamaUrl::rota('home', [], $request);

        return $this->gerekirseHttpsYap($anaSayfa, $request);
    }

    private function gerekirseHttpsYap(string $url, Request $request): string
    {
        $host = strtolower((string) $request->getHost());
        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
            return $url;
        }

        $parcalar = parse_url($url);
        if (! is_array($parcalar)) {
            return $url;
        }

        $sema = strtolower((string) ($parcalar['scheme'] ?? ''));
        if ($sema !== 'http') {
            return $url;
        }

        $urlHost = strtolower((string) ($parcalar['host'] ?? ''));
        if ($urlHost !== '' && $urlHost !== $host) {
            return $url;
        }

        return preg_replace('/^http:/i', 'https:', $url) ?? $url;
    }

    public function firmaKoduBulFormu(): View
    {
        return view('auth.firma-kodu-bul');
    }

    public function firmaKoduBul(Request $request): RedirectResponse
    {
        [$rules, $messages] = $this->recaptchaKurallariniEkle([
            'firma_adi' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'firma_adi.required' => 'Firma adi zorunludur.',
            'firma_adi.min' => 'En az 3 karakter girmelisiniz.',
        ]);

        $request->validate($rules, $messages);

        $anahtar = 'firma-kodu-bul:'.sha1((string) $request->ip());
        if (RateLimiter::tooManyAttempts($anahtar, 5)) {
            return back()->withErrors([
                'firma_adi' => 'Cok fazla deneme yaptiniz. Lutfen biraz sonra tekrar deneyin.',
            ])->withInput();
        }

        $recaptchaResult = $this->recaptchaDogrulamasiniKontrolEt($request);
        if ($recaptchaResult !== null) {
            return $recaptchaResult;
        }

        RateLimiter::hit($anahtar, 60);

        $firmalar = Firma::query()
            ->where('durum', 'aktif')
            ->where('ad', 'like', '%'.$request->string('firma_adi').'%')
            ->orderBy('ad')
            ->limit(5)
            ->get(['id', 'ad', 'firma_kodu']);

        if ($firmalar->isEmpty()) {
            return back()->withErrors([
                'firma_adi' => 'Bu isimle eslesen aktif firma bulunamadi.',
            ])->withInput();
        }

        return back()->with('bulunan_firma_kodlari', $firmalar->map(function (Firma $firma): array {
            return [
                'ad' => $firma->ad,
                'firma_kodu' => $firma->firma_kodu,
            ];
        })->all());
    }

    private function bruteForceKontrolEt(Request $request, string $kimlik, ?string $firmaKodu): ?RedirectResponse
    {
        $sertAnahtar = $this->sertBruteKey($request, $kimlik, $firmaKodu);
        if (RateLimiter::tooManyAttempts($sertAnahtar, 5)) {
            $kalan = RateLimiter::availableIn($sertAnahtar);
            app(SistemOlayServisi::class)->olayKaydet(
                'auth.tenant.locked',
                'warning',
                'Tenant giris kilitlendi.',
                ['ip' => (string) $request->ip(), 'kalan_saniye' => $kalan]
            );

            return back()->withErrors([
                'kullanici_adi_veya_eposta' => 'Hesap guvenligi nedeniyle giris gecici olarak kilitlendi. '.$kalan.' saniye sonra tekrar deneyin.',
            ])->withInput($request->except('sifre'));
        }

        $yavasAnahtar = $this->yavaslatmaKey($request, $kimlik, $firmaKodu);
        if (RateLimiter::tooManyAttempts($yavasAnahtar, 1)) {
            $kalan = RateLimiter::availableIn($yavasAnahtar);
            app(SistemOlayServisi::class)->olayKaydet(
                'auth.tenant.cooldown',
                'warning',
                'Tenant giris denemesi gecici yavaslatildi.',
                ['ip' => (string) $request->ip(), 'kalan_saniye' => $kalan]
            );

            return back()->withErrors([
                'kullanici_adi_veya_eposta' => 'Art arda hatali deneme algilandi. Lutfen '.$kalan.' saniye bekleyip tekrar deneyin.',
            ])->withInput($request->except('sifre'));
        }

        return null;
    }

    private function basarisizDenemeKaydet(Request $request, string $kimlik, ?string $firmaKodu): void
    {
        $sertAnahtar = $this->sertBruteKey($request, $kimlik, $firmaKodu);
        RateLimiter::hit($sertAnahtar, 15 * 60);
        app(SistemOlayServisi::class)->olayKaydet(
            'auth.tenant.failed_attempt',
            'warning',
            'Tenant giris basarisiz deneme.',
            ['ip' => (string) $request->ip()]
        );

        $deneme = RateLimiter::attempts($sertAnahtar);
        if ($deneme >= 3) {
            $bekleme = min(300, (int) (30 * (2 ** max(0, $deneme - 3))));
            RateLimiter::hit($this->yavaslatmaKey($request, $kimlik, $firmaKodu), $bekleme);
        }
    }

    private function bruteForceTemizle(Request $request, string $kimlik, ?string $firmaKodu): void
    {
        RateLimiter::clear($this->sertBruteKey($request, $kimlik, $firmaKodu));
        RateLimiter::clear($this->yavaslatmaKey($request, $kimlik, $firmaKodu));
    }

    private function sertBruteKey(Request $request, string $kimlik, ?string $firmaKodu): string
    {
        $firmaParcasi = mb_strtolower(trim((string) ($firmaKodu ?: 'otomatik')));
        $ham = mb_strtolower(trim($firmaParcasi.'|'.$kimlik.'|'.$request->ip()));

        return 'tenant-auth:hard:'.sha1($ham);
    }

    private function yavaslatmaKey(Request $request, string $kimlik, ?string $firmaKodu): string
    {
        $firmaParcasi = mb_strtolower(trim((string) ($firmaKodu ?: 'otomatik')));
        $ham = mb_strtolower(trim($firmaParcasi.'|'.$kimlik.'|'.$request->ip()));

        return 'tenant-auth:cooldown:'.sha1($ham);
    }

    private function telefonuBirlestir(string $ulkeKodu, string $telefon): string
    {
        $ulkeKoduRakam = preg_replace('/\D+/', '', $ulkeKodu) ?? '';
        $telefonRakam = preg_replace('/\D+/', '', $telefon) ?? '';

        if ($ulkeKoduRakam === '') {
            $ulkeKoduRakam = '90';
        }

        if ($ulkeKoduRakam === '90') {
            if (str_starts_with($telefonRakam, '90') && strlen($telefonRakam) >= 12) {
                $telefonRakam = substr($telefonRakam, 2);
            }
            if (str_starts_with($telefonRakam, '0')) {
                $telefonRakam = substr($telefonRakam, 1);
            }
        } else {
            $onEk = ltrim($ulkeKoduRakam, '0');
            if ($onEk !== '' && str_starts_with($telefonRakam, $onEk)) {
                $telefonRakam = substr($telefonRakam, strlen($onEk));
            }
            $telefonRakam = ltrim($telefonRakam, '0');
        }

        return '+'.$ulkeKoduRakam.$telefonRakam;
    }

    private function uyeOturumuAyarla(Request $request, bool $uyeOturumu): void
    {
        $request->session()->put(self::SESSION_UYE_OTURUMU, $uyeOturumu);
    }
}

