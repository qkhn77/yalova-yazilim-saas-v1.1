<?php

namespace Tests\Feature\UI;

use App\Filament\Clusters\Muhasebe\Resources\StokKategoriKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\StokKategoriKaynagi\Pages\CreateStokKategorisi;
use App\Filament\Resources\FirmaYonetimKaynagi;
use App\Filament\Resources\FirmaYonetimKaynagi\Pages\FirmaOlustur;
use App\Filament\Resources\FirmaYonetimKaynagi\RelationManagers\AboneliklerleIliskiYoneticisi;
use ReflectionClass;
use Tests\TestCase;

class UiAkisTemizlikTest extends TestCase
{
    public function test_firma_create_redirecti_listeye_doner(): void
    {
        $sayfa = new class extends FirmaOlustur
        {
            public function redirectUrlPublic(): string
            {
                return $this->getRedirectUrl();
            }
        };

        $this->assertSame(FirmaYonetimKaynagi::getUrl('index'), $sayfa->redirectUrlPublic());
    }

    public function test_stok_kategori_create_redirecti_listeye_doner(): void
    {
        $sayfa = new class extends CreateStokKategorisi
        {
            public function redirectUrlPublic(): string
            {
                return $this->getRedirectUrl();
            }
        };

        $this->assertSame(StokKategoriKaynagi::getUrl('index'), $sayfa->redirectUrlPublic());
    }

    public function test_duplicate_mesajlari_kullanici_dostu_turkce_metinlerdir(): void
    {
        $icerik = file_get_contents(base_path('app/Filament/Resources/FirmaIciKullaniciKaynagi/Pages/FirmaIciKullaniciOlustur.php'));

        $this->assertIsString($icerik);
        $this->assertStringContainsString('Bu kullanıcı adı zaten kullanılıyor.', $icerik);
        $this->assertStringContainsString('Bu e-posta adresi başka bir kullanıcıya ait.', $icerik);
        $this->assertStringContainsString('Bu telefon numarası zaten kayıtlı.', $icerik);
    }

    public function test_stok_sidebar_altinda_istenmeyen_linkler_yoktur(): void
    {
        $icerik = file_get_contents(base_path('resources/views/filament/components/custom-sidebar.blade.php'));

        $this->assertIsString($icerik);
        $this->assertStringContainsString('Depo Yönetimi', $icerik);
        $this->assertStringContainsString('Depo Stokları', $icerik);
        $this->assertStringContainsString('Depolar Arası Transfer', $icerik);
        $this->assertStringNotContainsString('muhasebe/sistem-olaylari', $icerik);
        $this->assertStringNotContainsString('muhasebe/is-analitigi', $icerik);
    }

    public function test_abonelik_durum_etiketi_karakter_uyumludur(): void
    {
        $method = (new ReflectionClass(AboneliklerleIliskiYoneticisi::class))
            ->getMethod('durumEtiketi');
        $method->setAccessible(true);

        $this->assertSame('Süresi doldu', $method->invoke(null, 'suresi_doldu'));
        $this->assertSame('Süresi doldu', $method->invoke(null, 'süresi_doldu'));
        $this->assertSame('Pasif', $method->invoke(null, 'pasif'));
        $this->assertSame('Beklemede', $method->invoke(null, 'beklemede'));
    }

    public function test_stok_listesinde_gorsel_kolonu_ve_placeholder_mevcut(): void
    {
        $resourceIcerik = file_get_contents(base_path('app/Filament/Clusters/Muhasebe/Resources/StokKartiKaynagi.php'));
        $viewIcerik = file_get_contents(base_path('resources/views/filament/muhasebe/columns/stok-gorsel.blade.php'));

        $this->assertIsString($resourceIcerik);
        $this->assertIsString($viewIcerik);
        $this->assertStringContainsString("ViewColumn::make('gorsel')", $resourceIcerik);
        $this->assertStringContainsString("->view('filament.muhasebe.columns.stok-gorsel')", $resourceIcerik);
        $this->assertStringContainsString('target="_blank"', $viewIcerik);
        $this->assertStringContainsString('Görsel yok', $viewIcerik);
    }

    public function test_create_another_davranisi_bozulmadan_korunur(): void
    {
        $firmaCreate = file_get_contents(base_path('app/Filament/Resources/FirmaYonetimKaynagi/Pages/FirmaOlustur.php'));
        $stokKategoriCreate = file_get_contents(base_path('app/Filament/Clusters/Muhasebe/Resources/StokKategoriKaynagi/Pages/CreateStokKategorisi.php'));

        $this->assertIsString($firmaCreate);
        $this->assertIsString($stokKategoriCreate);
        $this->assertStringNotContainsString('canCreateAnother', $firmaCreate);
        $this->assertStringNotContainsString('canCreateAnother', $stokKategoriCreate);
        $this->assertStringNotContainsString('preserveFormData', $firmaCreate);
        $this->assertStringNotContainsString('preserveFormData', $stokKategoriCreate);
    }
}
