<?php

namespace App\Console\Commands;

use App\Models\Firma;
use App\Models\FirmaKullanici;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\Teklif;
use App\Models\Post;
use App\Models\Project;
use App\Models\Restoran\RestoranMasasi;
use App\Models\Service;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use App\Services\TenantContextService;
use Illuminate\Console\Command;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PerformansWarmupCommand extends Command
{
    protected $signature = 'performans:warmup
        {--url= : APP_URL yerine kullanilacak taban URL}
        {--user-id= : Warmup icin kullanilacak kullanici ID}
        {--firma-id= : Warmup icin kullanilacak firma ID}
        {--record-id= : Teknik servis edit warmup kayit ID}
        {--only= : Sadece etiketi bu metni iceren URLleri calistir}
        {--runs=1 : Her URL icin tekrar sayisi}
        {--timeout=25 : Her HTTP istegi icin saniye cinsinden timeout}
        {--body-dir= : Yanıt HTMLlerini analiz icin kaydedecek klasor}';

    protected $description = 'Apache/opcache soguk baslangicini azaltmak icin kritik sayfalari guvenli GET istekleriyle isitir.';

    public function handle(): int
    {
        $firmaKullanici = $this->firmaKullanicisiniBul();
        if (! $firmaKullanici) {
            $this->error('Warmup icin aktif firma kullanicisi bulunamadi.');

            return self::FAILURE;
        }

        $kullanici = User::query()->find((int) $firmaKullanici->kullanici_id);
        $firma = Firma::query()->find((int) $firmaKullanici->firma_id);

        if (! $kullanici || ! $firma) {
            $this->error('Warmup kullanicisi veya firmasi bulunamadi.');

            return self::FAILURE;
        }

        $cookie = $this->oturumCookieOlustur($kullanici, $firma, $firmaKullanici);
        $urls = $this->warmupUrlListesi($firma, (int) ($this->option('record-id') ?: 0));
        $only = trim((string) ($this->option('only') ?: ''));
        if ($only !== '') {
            $urls = array_filter(
                $urls,
                fn (string $etiket): bool => str_contains($etiket, $only),
                ARRAY_FILTER_USE_KEY
            );

            if ($urls === []) {
                $this->warn('Bu filtreyle eslesen warmup URLsi bulunamadi: '.$only);

                return self::SUCCESS;
            }
        }

        $timeout = max(3, (int) $this->option('timeout'));
        $runs = max(1, (int) $this->option('runs'));

        foreach ($urls as $etiket => $url) {
            $sureler = [];
            $status = null;
            $byte = 0;
            $yonlendirmeBilgisi = '';
            $hata = null;

            for ($run = 1; $run <= $runs; $run++) {
                $baslangic = hrtime(true);
                $adminIstegi = str_starts_with($etiket, 'admin.');
                $headers = ['Accept' => 'text/html'];

                if ($adminIstegi) {
                    $headers['Cookie'] = $cookie;
                }

                try {
                    $istek = Http::timeout($timeout)->withHeaders($headers);

                    if ($adminIstegi) {
                        $istek = $istek->withoutRedirecting();
                    }

                    $yanit = $istek->get($url);
                    $sureMs = (hrtime(true) - $baslangic) / 1_000_000;
                    $sureler[] = $sureMs;
                    $status = $yanit->status();
                    $byte = strlen($yanit->body());
                    $this->yanitGovdesiniKaydet($etiket, $yanit->body());
                    $yonlendirmeBilgisi = $this->yonlendirmeBilgisi($yanit->status(), $yanit->header('Location'));
                } catch (\Throwable $e) {
                    $sureMs = (hrtime(true) - $baslangic) / 1_000_000;
                    $sureler[] = $sureMs;
                    $status = 'hata';
                    $hata = $e->getMessage();
                }
            }

            $ortalama = array_sum($sureler) / max(1, count($sureler));
            $runListesi = implode('|', array_map(fn (float $sure): string => (string) round($sure), $sureler));
            $metin = sprintf(
                '%s | %s | %.0f ms | min %.0f | max %.0f | runs %s | %d byte%s',
                $etiket,
                $status,
                $ortalama,
                min($sureler),
                max($sureler),
                $runListesi,
                $byte,
                $yonlendirmeBilgisi
            );

            $hata ? $this->warn($metin.' | '.$hata) : $this->line($metin);
        }

        return self::SUCCESS;
    }

    private function yanitGovdesiniKaydet(string $etiket, string $govde): void
    {
        $klasor = trim((string) ($this->option('body-dir') ?: ''));
        if ($klasor === '') {
            return;
        }

        if (! is_dir($klasor)) {
            mkdir($klasor, 0775, true);
        }

        file_put_contents(
            rtrim($klasor, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.Str::slug($etiket).'.html',
            $govde
        );
    }

    private function firmaKullanicisiniBul(): ?FirmaKullanici
    {
        $sorgu = FirmaKullanici::query()
            ->withoutGlobalScopes()
            ->where('durum', 'aktif')
            ->whereNull('deleted_at');

        if ($this->option('user-id')) {
            $sorgu->where('kullanici_id', (int) $this->option('user-id'));
        }

        if ($this->option('firma-id')) {
            $sorgu->where('firma_id', (int) $this->option('firma-id'));
        }

        return $sorgu
            ->whereNotNull('rol_id')
            ->orderByDesc('firma_id')
            ->orderBy('id')
            ->first();
    }

    private function oturumCookieOlustur(User $kullanici, Firma $firma, FirmaKullanici $firmaKullanici): string
    {
        $request = Request::create('/', 'GET');
        $session = app('session')->driver();
        $session->setId(Str::random(40));
        $session->start();
        $request->setLaravelSession($session);

        app()->instance('request', $request);
        app('url')->setRequest($request);

        Auth::login($kullanici);
        $request->setUserResolver(fn (): User => $kullanici);

        app(TenantContextService::class)->firmaAyarla(
            $firma,
            $firmaKullanici->rol_id ? (int) $firmaKullanici->rol_id : null,
            (int) $firmaKullanici->id
        );

        $session->save();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $cookieAdi = (string) config('session.cookie');
        $cookieDegeri = app('encrypter')->encrypt(
            CookieValuePrefix::create($cookieAdi, app('encrypter')->getKey()).$session->getId(),
            false
        );

        return $cookieAdi.'='.$cookieDegeri;
    }

    /**
     * @return array<string, string>
     */
    private function warmupUrlListesi(Firma $firma, int $recordId): array
    {
        $baseUrl = $this->tabanUrl();
        $adminBase = $baseUrl.'/'.trim(AdminPanelProvider::adminPath(), '/');

        if ($recordId < 1) {
            $recordId = (int) TeknikServisKaydi::query()
                ->withoutGlobalScopes()
                ->where('firma_id', (int) $firma->id)
                ->orderByDesc('id')
                ->value('id');
        }

        $urls = [
            'front.home' => $baseUrl.'/',
            'front.servisler' => $baseUrl.'/Servisler',
            'front.projeler' => $baseUrl.'/Projeler',
            'front.blog' => $baseUrl.'/blog',
            'front.urunler' => $baseUrl.'/urunler',
            'front.firma-giris' => $baseUrl.'/giris',
            'front.firma-kodu-bul' => $baseUrl.'/firma-kodumu-bul',
            'front.uye-giris' => $baseUrl.'/uye-giris',
            'front.yonetici-giris' => $baseUrl.'/yonetici-giris',
            'admin.dashboard' => $adminBase,
            'admin.ayarlar.mesaj-merkezi' => $adminBase.'/ayarlar/mesaj-merkezi',
            'admin.ayarlar.kullanicilar' => $adminBase.'/ayarlar/kullanici-ayarlari/kullanicilar',
            'admin.ayarlar.kullanici-gruplari' => $adminBase.'/ayarlar/kullanici-ayarlari/kullanici-gruplari',
            'admin.firma-ayarlari' => $adminBase.'/firma-ayarlari',
            'admin.sistem.firmalar' => $adminBase.'/sistem-firmalar',
            'admin.sistem.kullanicilar' => $adminBase.'/sistem-kullanicilar',
            'admin.sistem.roller' => $adminBase.'/sistem-roller',
            'admin.sistem.yetkiler' => $adminBase.'/sistem-yetkiler',
            'admin.sistem.planlar' => $adminBase.'/sistem-planlar',
            'admin.sistem.denetim' => $adminBase.'/sistem-denetim-kayitlari',
            'admin.personel.cluster' => $adminBase.'/personel-takip',
            'admin.personel.personeller' => $adminBase.'/personel-takip/personeller',
            'admin.personel.giris-cikis' => $adminBase.'/personel-takip/giris-cikis',
            'admin.personel.vardiyalar' => $adminBase.'/personel-takip/vardiyalar',
            'admin.personel.izinler' => $adminBase.'/personel-takip/izinler',
            'admin.personel.avanslar' => $adminBase.'/personel-takip/avanslar',
            'admin.personel.raporlar' => $adminBase.'/personel-takip/raporlar/personel-ozeti',
            'admin.etici.cluster' => $adminBase.'/e-ticaret',
            'admin.etici.siparisler' => $adminBase.'/siparisler',
            'admin.etici.siparisler.basarisiz' => $adminBase.'/siparisler/basarisiz',
            'admin.etici.musteri-mesajlari' => $adminBase.'/e-ticaret/mesaj-yonetimi/musteri-mesajlari',
            'admin.etici.urun-mesajlari' => $adminBase.'/e-ticaret/mesaj-yonetimi/urun-mesajlari',
            'admin.etici.kampanya' => $adminBase.'/e-ticaret/kampanya-yonetimi',
            'admin.etici.kargo' => $adminBase.'/e-ticaret/kargo-yonetimi',
            'admin.etici.odeme' => $adminBase.'/e-ticaret/odeme-yonetimi',
            'admin.etici.bildirim-loglari' => $adminBase.'/e-ticaret/bildirim-yonetimi/loglar',
            'admin.web.cluster' => $adminBase.'/web',
            'admin.web.servisler' => $adminBase.'/web/servisler/web-servis-listesi',
            'admin.web.projeler' => $adminBase.'/web/projeler/liste',
            'admin.web.blog' => $adminBase.'/web/bloglar/blog-listesi',
            'admin.web.bilgi-sayfalari' => $adminBase.'/web/sayfalar/bilgi-sayfalari',
            'admin.web.urunler' => $adminBase.'/web/urunler/urun-listesi',
            'admin.web.ayarlar' => $adminBase.'/web/web-ayarlar/web-genel-ayarlar',
            'admin.web.moduller' => $adminBase.'/web/web-moduller',
            'admin.teknik-servis.cluster' => $adminBase.'/teknik-servis',
            'admin.teknik-servis.ozet' => $adminBase.'/teknik-servis/ozet',
            'admin.teknik-servis.liste' => $adminBase.'/teknik-servis/servis-kayitlari',
            'admin.teknik-servis.liste.yeni' => $adminBase.'/teknik-servis/servis-kayitlari/liste/yeni',
            'admin.teknik-servis.liste.acik' => $adminBase.'/teknik-servis/servis-kayitlari/liste/acik',
            'admin.teknik-servis.liste.tezgahta' => $adminBase.'/teknik-servis/servis-kayitlari/liste/tezgahta',
            'admin.teknik-servis.liste.teslim-bekleyen' => $adminBase.'/teknik-servis/servis-kayitlari/liste/teslim-bekleyen',
            'admin.teknik-servis.olustur.arizali-cihaz' => $adminBase.'/teknik-servis/servis-kayitlari/olustur/arizali-cihaz',
            'admin.teknik-servis.olustur.bakim' => $adminBase.'/teknik-servis/servis-kayitlari/olustur/bakim',
            'admin.teknik-servis.olustur.dis-servis' => $adminBase.'/teknik-servis/servis-kayitlari/olustur/dis-servis',
            'admin.muhasebe.cluster' => $adminBase.'/muhasebe',
            'admin.muhasebe.panel' => $adminBase.'/muhasebe/muhasebe-panel',
            'admin.muhasebe.finans-panel' => $adminBase.'/muhasebe/finans/finans-panel',
            'admin.muhasebe.faturalar' => $adminBase.'/muhasebe/faturalar/tum-faturalar',
            'admin.muhasebe.faturalar.kaynak-index' => $adminBase.'/muhasebe/fatura-kaynagis',
            'admin.muhasebe.fatura.olustur.giden' => $adminBase.'/muhasebe/fatura-kaynagis/create/giden-fatura',
            'admin.muhasebe.fatura.olustur.gelen' => $adminBase.'/muhasebe/fatura-kaynagis/create/gelen-fatura',
            'admin.muhasebe.cariler' => $adminBase.'/muhasebe/cari-yonetimi/cariler',
            'admin.muhasebe.stok-listesi' => $adminBase.'/muhasebe/stok/stok-listesi',
            'admin.muhasebe.finans-hareketleri' => $adminBase.'/muhasebe/finans/finans-hareketleri',
            'admin.muhasebe.tahsilat-olustur' => $adminBase.'/muhasebe/finans/tahsilat-olustur',
            'admin.muhasebe.odeme-olustur' => $adminBase.'/muhasebe/finans/odeme-olustur',
            'admin.muhasebe.vade-takibi' => $adminBase.'/muhasebe/finans/vade-takibi',
            'admin.proje-yonetimi.projeler' => $adminBase.'/proje-yonetimi/projeler',
            'admin.proje-yonetimi.raporlar' => $adminBase.'/proje-yonetimi/raporlar',
            'admin.masraf-takip.masraflar' => $adminBase.'/masraf-takip/masraflar',
            'admin.masraf-takip.raporlar' => $adminBase.'/masraf-takip/raporlar',
            'admin.muhasebe.hizli-satis' => $adminBase.'/muhasebe/satis/hizli-satis',
            'admin.restoran.cluster' => $adminBase.'/restoran',
            'admin.restoran.masa-ekrani' => $adminBase.'/restoran/masa-ekrani',
            'admin.restoran.mutfak' => $adminBase.'/restoran/mutfak',
            'admin.restoran.paket-servis' => $adminBase.'/restoran/paket-servis',
            'admin.restoran.raporlar' => $adminBase.'/restoran/raporlar/genel',
            'admin.restoran.gun-sonu' => $adminBase.'/restoran/raporlar/gun-sonu',
            'admin.restoran.adisyonlar' => $adminBase.'/restoran/adisyonlar',
            'admin.restoran.masalar' => $adminBase.'/restoran/masalar',
            'admin.restoran.menu-urunleri' => $adminBase.'/restoran/menu-urunleri',
            'admin.restoran.receteler' => $adminBase.'/restoran/receteler',
            'admin.teklif.liste' => $adminBase.'/teklif-yonetimi/teklifler',
            'admin.teklif.olustur' => $adminBase.'/teklif-yonetimi/teklifler/create',
            'admin.teklif.sablonlar' => $adminBase.'/teklif-yonetimi/sablonlar',
        ];

        $serviceSlug = Service::query()->where('is_active', true)->orderByDesc('id')->value('slug');
        if (is_string($serviceSlug) && $serviceSlug !== '') {
            $urls['front.servis.detay'] = $baseUrl.'/Servisler/'.$serviceSlug;
        }

        $projectSlug = Project::query()->where('is_active', true)->orderByDesc('id')->value('slug');
        if (is_string($projectSlug) && $projectSlug !== '') {
            $urls['front.proje.detay'] = $baseUrl.'/Projeler/'.$projectSlug;
        }

        $postSlug = Post::query()->where('is_published', true)->orderByDesc('id')->value('slug');
        if (is_string($postSlug) && $postSlug !== '') {
            $urls['front.blog.detay'] = $baseUrl.'/blog/'.$postSlug;
        }

        $urunSlug = StokKarti::query()
            ->withoutGlobalScopes()
            ->where('firma_id', (int) $firma->id)
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->orderByDesc('id')
            ->value('slug');
        if (is_string($urunSlug) && $urunSlug !== '') {
            $urls['front.urun.detay'] = $baseUrl.'/urun/'.$urunSlug;
        }

        if (is_string($firma->firma_kodu) && $firma->firma_kodu !== '') {
            $urls['front.restoran.qr-menu'] = $baseUrl.'/restoran/qr-menu/'.$firma->firma_kodu;

            $masaQrKodu = RestoranMasasi::query()
                ->withoutGlobalScopes()
                ->where('firma_id', (int) $firma->id)
                ->whereNotNull('qr_siparis_kodu')
                ->where('qr_siparis_kodu', '!=', '')
                ->where('aktif_mi', true)
                ->orderByDesc('id')
                ->value('qr_siparis_kodu');

            if (is_string($masaQrKodu) && $masaQrKodu !== '') {
                $urls['front.restoran.qr-masa'] = $baseUrl.'/restoran/qr-menu/'.$firma->firma_kodu.'/masalar/'.$masaQrKodu;
                $urls['front.restoran.qr-adisyon'] = $baseUrl.'/restoran/qr-menu/'.$firma->firma_kodu.'/masalar/'.$masaQrKodu.'/adisyon';
            }
        }

        if ($recordId > 0) {
            $urls['admin.teknik-servis.edit'] = $adminBase.'/teknik-servis/servis-kayitlari/'.$recordId.'/duzenle';
        }

        $teklifId = (int) Teklif::query()
            ->withoutGlobalScopes()
            ->where('firma_id', (int) $firma->id)
            ->orderByDesc('id')
            ->value('id');
        if ($teklifId > 0) {
            $urls['admin.teklif.edit'] = $adminBase.'/teklif-yonetimi/teklifler/'.$teklifId.'/edit';
            $urls['admin.teklif.onizleme'] = $adminBase.'/teklif-yonetimi/teklifler/'.$teklifId;
        }

        return $urls;
    }

    private function tabanUrl(): string
    {
        $url = trim((string) ($this->option('url') ?: config('app.url', 'http://localhost')));

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = 'http://'.$url;
        }

        return rtrim($url, '/');
    }

    private function yonlendirmeBilgisi(int $status, ?string $location): string
    {
        if (! in_array($status, [301, 302, 303, 307, 308], true)) {
            return '';
        }

        return ' | redirect: '.($location ?: '-');
    }
}
