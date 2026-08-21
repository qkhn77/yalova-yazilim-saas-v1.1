<?php

namespace App\Support;

use App\Filament\Clusters\Ayarlar\Pages\Kullanicilar;
use App\Filament\Clusters\Muhasebe\Pages\Cari;
use App\Filament\Clusters\TeknikServis\Pages\ServisDashboard;
use App\Filament\Clusters\Web\Pages\BilgiSayfalari;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use App\Services\SidebarService;
use App\Services\TenantContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Tek Filament paneli — firma ve yönetici girişleri sonrası ortak hedef URL.
 */
class PanelYonlendirme
{
    public static function anaSayfaUrl(?Request $istek = null): string
    {
        return AdminPanelProvider::adminUrl();
    }

    /**
     * Oturumdaki url.intended yalnızca panel yolu içindeyse kullanılır.
     * Aksi halde (ör. önce ana sayfa / ziyaret edildiyse) ana sayfaya düşülmez, panel açılır.
     */
    public static function guvenliIntendedIlePanel(Request $istek): RedirectResponse
    {
        $varsayilan = self::varsayilanPanelUrl($istek);
        $hedef = $istek->session()->get('url.intended');

        if (! is_string($hedef) || $hedef === '') {
            return redirect()->to($varsayilan);
        }

        if (! self::urlPanelIciMi($hedef, $istek)) {
            $istek->session()->forget('url.intended');

            return redirect()->to($varsayilan);
        }

        if (self::intendedKumeKokuMu($hedef, $istek)) {
            $istek->session()->forget('url.intended');

            return redirect()->to($varsayilan);
        }

        return redirect()->intended($varsayilan);
    }

    public static function varsayilanPanelUrl(Request $istek): string
    {
        $kullanici = Auth::user();

        if (! $kullanici instanceof User) {
            return UygulamaUrl::rota('tenant.login', [], $istek);
        }

        $superAdminMi = (bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false);
        if ($superAdminMi) {
            return self::anaSayfaUrl($istek);
        }

        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if (! $firmaId) {
            return UygulamaUrl::rota('tenant.login', [], $istek);
        }

        return self::anaSayfaUrl($istek);
    }

    public static function urlPanelIciMi(string $url, Request $istek): bool
    {
        $parcalar = parse_url($url);
        if ($parcalar === false) {
            return false;
        }

        if (self::httpsZorunluMu($istek) && isset($parcalar['scheme']) && strtolower((string) $parcalar['scheme']) !== 'https') {
            return false;
        }

        if (isset($parcalar['host']) && strcasecmp((string) $parcalar['host'], $istek->getHost()) !== 0) {
            return false;
        }

        $yol = $parcalar['path'] ?? '/';
        $yolNorm = '/'.trim($yol, '/');
        $panelOnEki = self::panelYolu($istek);

        return $yolNorm === $panelOnEki || str_starts_with($yolNorm, $panelOnEki.'/');
    }

    /**
     * Yol, panel + tek küme segmentinden mi oluşuyor? (örn. /admin/muhasebe — alt sayfa yok)
     */
    public static function intendedKumeKokuMu(string $url, Request $istek): bool
    {
        $parcalar = parse_url($url);
        if ($parcalar === false) {
            return false;
        }

        if (self::httpsZorunluMu($istek) && isset($parcalar['scheme']) && strtolower((string) $parcalar['scheme']) !== 'https') {
            return false;
        }

        if (isset($parcalar['host']) && strcasecmp((string) $parcalar['host'], $istek->getHost()) !== 0) {
            return false;
        }

        $yol = trim((string) ($parcalar['path'] ?? '/'), '/');
        if ($yol === '') {
            return false;
        }

        $parca = explode('/', $yol);
        $baseParcalar = array_values(array_filter(explode('/', trim((string) $istek->getBaseUrl(), '/'))));
        if ($baseParcalar !== [] && array_slice($parca, 0, count($baseParcalar)) === $baseParcalar) {
            $parca = array_slice($parca, count($baseParcalar));
        }

        $panel = trim(AdminPanelProvider::adminPath(), '/');
        $i = array_search($panel, $parca, true);
        if ($i === false) {
            return false;
        }

        // [... , panel, tek-parça-küme]
        return isset($parca[$i + 1]) && ! isset($parca[$i + 2]);
    }

    private static function httpsZorunluMu(Request $istek): bool
    {
        $host = strtolower((string) $istek->getHost());
        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
            return false;
        }

        $hamDeger = env('APP_FORCE_HTTPS');
        $zorla = filter_var($hamDeger, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($zorla === true) {
            return true;
        }

        if ($zorla === false) {
            return ! app()->environment('local');
        }

        return true;
    }

    private static function panelYolu(Request $istek): string
    {
        $yol = parse_url(AdminPanelProvider::adminUrl(), PHP_URL_PATH);

        return is_string($yol) && $yol !== '' ? $yol : '/'.trim(AdminPanelProvider::adminPath(), '/');
    }
}

