<?php

namespace Tests\Feature\UI;

use Tests\TestCase;

class TurkceKarakterButunluguTest extends TestCase
{
    public function test_tum_filament_php_dosyalari_utf8_ve_bomsuzdur(): void
    {
        $files = $this->collectFiles(app_path('Filament'), ['php']);

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            $this->assertIsString($contents, 'Dosya okunamadi: '.$file);
            $this->assertTrue(mb_check_encoding($contents, 'UTF-8'), 'Dosya UTF-8 degil: '.$file);
            $this->assertFalse(str_starts_with($contents, "\xEF\xBB\xBF"), 'Dosya BOM ile kaydedilmis: '.$file);
        }
    }

    public function test_urun_ve_gorsel_akisi_dosyalarinda_metinler_utf8_ve_temizdir(): void
    {
        $files = [
            app_path('Console/Commands/ImportHikvisionWebProductsCommand.php'),
            app_path('Models/Muhasebe/StokKarti.php'),
            app_path('Models/Muhasebe/StokKartiGorseli.php'),
            app_path('Modules/Urun/Servisler/UrunServisi.php'),
            app_path('Filament/Clusters/Muhasebe/Resources/StokKartiKaynagi/Pages/ViewStokKarti.php'),
            resource_path('views/front/urunler/index.blade.php'),
            resource_path('views/front/urunler/kategori.blade.php'),
            resource_path('views/front/urunler/detay.blade.php'),
        ];

        $suspiciousTokens = ['Ãƒ', 'Ã„', 'Ã…', 'Ã¢â‚¬â€', 'Ã¢â‚¬Å“', 'Ã¢â‚¬', 'Ã‚'];
        $failures = [];

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            $this->assertIsString($contents, 'Dosya okunamadi: '.$file);

            if (! mb_check_encoding($contents, 'UTF-8')) {
                $failures[] = $file.' UTF-8 degil.';
                continue;
            }

            foreach ($suspiciousTokens as $token) {
                if (str_contains($contents, $token)) {
                    $failures[] = $file.' icinde supheli karakter dizisi bulundu: '.$token;
                }
            }
        }

        $this->assertSame([], $failures, implode(PHP_EOL, $failures));
    }

    public function test_stok_karti_panelindeki_gorsel_metni_turkce_olarak_korunur(): void
    {
        $contents = file_get_contents(app_path('Filament/Clusters/Muhasebe/Resources/StokKartiKaynagi.php'));

        $this->assertIsString($contents);
        $this->assertTrue(mb_check_encoding($contents, 'UTF-8'));
        $this->assertStringContainsString('Ürün Görselleri', $contents);
        $this->assertStringContainsString('Görsel Ekle', $contents);
        $this->assertStringContainsString('Kapak Görsel', $contents);
        $this->assertStringContainsString('Görselleri tek tek ekleyebilir, önizleyebilir, sıralayabilir ve kapak görsel seçebilirsiniz.', $contents);
        $this->assertStringNotContainsString('Urun Gorselleri', $contents);
        $this->assertStringNotContainsString('Kapak Gorsel', $contents);
    }

    public function test_panelde_temizlenen_ekranlar_turkce_metinleri_korur(): void
    {
        $assertions = [
            app_path('Filament/Clusters/Muhasebe/Pages/FinansDashboardSayfasi.php') => [
                'Tahsilat ve ödeme özetleri, kasa/banka/POS durumu ve son hareketler.',
                'Ödeme ekle',
                'Tüm finans hareketleri',
            ],
            app_path('Filament/Clusters/Muhasebe/Pages/GelenIadeFaturasiSayfasi.php') => [
                'Gelen İade Faturaları',
                'Gelen İade Faturası Ekle',
            ],
            app_path('Filament/Clusters/Muhasebe/Pages/GidenIadeFaturasiSayfasi.php') => [
                'Giden İade Faturaları',
                'Giden İade Faturası Ekle',
            ],
            app_path('Filament/Clusters/Muhasebe/Resources/FaturaKaynagi/Pages/CreateGelenIadeFaturasi.php') => [
                'Gelen İade Faturası Ekle',
            ],
            app_path('Filament/Clusters/Muhasebe/Resources/FaturaKaynagi/Pages/CreateGidenIadeFaturasi.php') => [
                'Giden İade Faturası Ekle',
            ],
            app_path('Filament/Clusters/Muhasebe/Resources/FaturaKaynagi/Pages/CreateGiderFaturasi.php') => [
                'Gider Faturası Ekle',
            ],
            app_path('Filament/Clusters/TeknikServis/Kaynaklar/TeknikServisAyarSayfaErisimleri.php') => [
                'Genel ayarlar ve şablon sayfaları.',
            ],
            app_path('Filament/Clusters/TeknikServis/Kaynaklar/TeknikServisFilamentErisimYardimcisi.php') => [
                'Teknik Servis Filament ekranları için ortak erişim kontrolü',
            ],
            app_path('Filament/Clusters/TeknikServis/Kaynaklar/TeknikServisSayfaErisimleri.php') => [
                'Özet ve operasyon sayfaları için',
            ],
            app_path('Filament/Clusters/TeknikServis/Resources/Concerns/TeknikServisTanimKaynakErisimi.php') => [
                'Teknik servis tanım kartları',
                'oluştur/sil -> tanım güncelle',
            ],
            resource_path('views/filament/components/custom-sidebar.blade.php') => [
                "POS'lar",
            ],
            app_path('Filament/Clusters/Muhasebe/Resources/StokKategoriKaynagi.php') => [
                'Sistem sabit tanımı',
                'Üst kategori',
                'Açıklama',
                'Kayıt sayısı',
            ],
            app_path('Filament/Clusters/Web/Resources/UrunKaynagi.php') => [
                'Ürün',
                'Ürünler',
            ],
            app_path('Filament/Clusters/Web/Resources/UrunKategoriKaynagi.php') => [
                'Ürün kategorisi',
                'Ürün kategorileri',
            ],
        ];

        foreach ($assertions as $file => $expectedTexts) {
            $contents = file_get_contents($file);

            $this->assertIsString($contents, 'Dosya okunamadi: '.$file);
            $this->assertTrue(mb_check_encoding($contents, 'UTF-8'), 'Dosya UTF-8 degil: '.$file);

            foreach ($expectedTexts as $text) {
                $this->assertStringContainsString($text, $contents, $file.' icinde beklenen metin bulunamadi: '.$text);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function collectFiles(string $path, array $extensions): array
    {
        if (is_file($path)) {
            return [$path];
        }

        if (! is_dir($path)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $realPath = $item->getPathname();
            foreach ($extensions as $extension) {
                if (str_ends_with($realPath, $extension)) {
                    $files[] = $realPath;
                    break;
                }
            }
        }

        sort($files);

        return $files;
    }
}
