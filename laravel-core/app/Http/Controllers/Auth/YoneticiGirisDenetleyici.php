<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\HandlesLoginRecaptcha;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SistemOlayServisi;
use App\Services\TenantContextService;
use App\Support\KullaniciTablosuYardimcisi;
use App\Support\PanelYonlendirme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class YoneticiGirisDenetleyici extends Controller
{
    use HandlesLoginRecaptcha;

    public function girisFormu(Request $request, TenantContextService $tenantBaglam): View|RedirectResponse
    {
        if (Auth::check()) {
            $kullanici = Auth::user();
            $yoneticiMi = (bool) ($kullanici?->super_admin_mi ?? false) || (bool) ($kullanici?->is_admin ?? false);
            if ($yoneticiMi) {
                return PanelYonlendirme::guvenliIntendedIlePanel($request);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $tenantBaglam->temizle();
        }

        return view('auth.yonetici-giris');
    }

    public function giris(Request $request, TenantContextService $tenantBaglam): RedirectResponse
    {
        [$rules, $messages] = $this->recaptchaKurallariniEkle([
            'kullanici_adi_veya_eposta' => ['required', 'string', 'max:255'],
            'sifre' => ['required', 'string', 'max:255'],
        ], [
            'kullanici_adi_veya_eposta.required' => 'Kullanici adi veya e-posta zorunludur.',
            'sifre.required' => 'Sifre zorunludur.',
        ]);

        $dogrulanmis = $request->validate($rules, $messages);

        $kimlik = trim((string) $dogrulanmis['kullanici_adi_veya_eposta']);

        $bruteResult = $this->bruteForceKontrolEt($request, $kimlik);
        if ($bruteResult !== null) {
            return $bruteResult;
        }

        $recaptchaResult = $this->recaptchaDogrulamasiniKontrolEt($request);
        if ($recaptchaResult !== null) {
            return $recaptchaResult;
        }

        $kullanici = $this->kullaniciKimligiyleBul($kimlik);

        if (! $kullanici || ! Hash::check($dogrulanmis['sifre'], $kullanici->password)) {
            $this->basarisizDenemeKaydet($request, $kimlik);

            return back()->withErrors([
                'kullanici_adi_veya_eposta' => 'Kullanici bilgisi veya sifre hatali.',
            ])->withInput($request->except('sifre'));
        }

        $yoneticiMi = (bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false);
        if (! $yoneticiMi) {
            $this->basarisizDenemeKaydet($request, $kimlik);

            return back()->withErrors([
                'kullanici_adi_veya_eposta' => 'Bu giris yalnizca sistem yoneticileri icindir. Firma kullanicilari icin /giris adresini kullanin.',
            ])->withInput($request->except('sifre'));
        }

        $this->bruteForceTemizle($request, $kimlik);

        $tenantBaglam->temizle();

        Auth::login($kullanici, $request->boolean('beni_hatirla'));
        $request->session()->regenerate();

        return PanelYonlendirme::guvenliIntendedIlePanel($request);
    }

    private function kullaniciKimligiyleBul(string $kimlik): ?User
    {
        $tablo = (new User)->getTable();

        $sorgu = User::query()->withoutGlobalScopes();
        KullaniciTablosuYardimcisi::kullaniciSilinmemisFiltresiUygula($sorgu, $tablo);
        KullaniciTablosuYardimcisi::kullaniciAktifFiltresiUygula($sorgu, $tablo);

        return $sorgu
            ->where(function ($sorgu) use ($kimlik): void {
                $sorgu->where('kullanici_adi', $kimlik)
                    ->orWhere('email', $kimlik);
            })
            ->first();
    }

    private function bruteForceKontrolEt(Request $request, string $kimlik): ?RedirectResponse
    {
        $sertAnahtar = $this->sertBruteKey($request, $kimlik);
        if (RateLimiter::tooManyAttempts($sertAnahtar, 5)) {
            $kalan = RateLimiter::availableIn($sertAnahtar);
            app(SistemOlayServisi::class)->olayKaydet(
                'auth.admin.locked',
                'warning',
                'Yonetici giris kilitlendi.',
                ['ip' => (string) $request->ip(), 'kalan_saniye' => $kalan]
            );

            return back()->withErrors([
                'kullanici_adi_veya_eposta' => 'Hesap guvenligi nedeniyle giris gecici olarak kilitlendi. '.$kalan.' saniye sonra tekrar deneyin.',
            ])->withInput($request->except('sifre'));
        }

        $yavasAnahtar = $this->yavaslatmaKey($request, $kimlik);
        if (RateLimiter::tooManyAttempts($yavasAnahtar, 1)) {
            $kalan = RateLimiter::availableIn($yavasAnahtar);
            app(SistemOlayServisi::class)->olayKaydet(
                'auth.admin.cooldown',
                'warning',
                'Yonetici giris denemesi gecici yavaslatildi.',
                ['ip' => (string) $request->ip(), 'kalan_saniye' => $kalan]
            );

            return back()->withErrors([
                'kullanici_adi_veya_eposta' => 'Art arda hatali deneme algilandi. Lutfen '.$kalan.' saniye bekleyip tekrar deneyin.',
            ])->withInput($request->except('sifre'));
        }

        return null;
    }

    private function basarisizDenemeKaydet(Request $request, string $kimlik): void
    {
        $sertAnahtar = $this->sertBruteKey($request, $kimlik);
        RateLimiter::hit($sertAnahtar, 15 * 60);
        app(SistemOlayServisi::class)->olayKaydet(
            'auth.admin.failed_attempt',
            'warning',
            'Yonetici giris basarisiz deneme.',
            ['ip' => (string) $request->ip()]
        );

        $deneme = RateLimiter::attempts($sertAnahtar);
        if ($deneme >= 3) {
            $bekleme = min(300, (int) (30 * (2 ** max(0, $deneme - 3))));
            RateLimiter::hit($this->yavaslatmaKey($request, $kimlik), $bekleme);
        }
    }

    private function bruteForceTemizle(Request $request, string $kimlik): void
    {
        RateLimiter::clear($this->sertBruteKey($request, $kimlik));
        RateLimiter::clear($this->yavaslatmaKey($request, $kimlik));
    }

    private function sertBruteKey(Request $request, string $kimlik): string
    {
        $ham = mb_strtolower(trim($kimlik.'|'.$request->ip()));

        return 'admin-auth:hard:'.sha1($ham);
    }

    private function yavaslatmaKey(Request $request, string $kimlik): string
    {
        $ham = mb_strtolower(trim($kimlik.'|'.$request->ip()));

        return 'admin-auth:cooldown:'.sha1($ham);
    }
}
