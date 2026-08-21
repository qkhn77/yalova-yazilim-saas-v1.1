<?php

namespace Tests\Feature\Hardening;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Services\AuditOlaySunumServisi;
use App\Services\AuditTrailServisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class AuditUxCoveragePolishTest extends TestCase
{
    use RefreshDatabase;

    private function firmaOlustur(string $kod): Firma
    {
        return Firma::query()->create([
            'ad' => 'Test '.$kod,
            'kisa_ad' => $kod,
            'firma_kodu' => $kod.'-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
    }

    public function test_fatura_kalemi_degisiminde_audit_kaydi_uretilir(): void
    {
        $firma = $this->firmaOlustur('AKP');
        $cari = Cari::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'kod' => 'C-'.uniqid(),
            'ad' => 'Cari',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);

        $fatura = Fatura::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'tur' => FaturaTuru::Giden->value,
            'durum' => FaturaDurumu::Taslak->value,
            'tarih' => now(),
            'ara_toplam' => '100.00',
            'kdv_toplam' => '20.00',
            'genel_toplam' => '120.00',
            'odenecek_tutar' => '120.00',
            'acik_tutar' => '120.00',
            'odendi_tutari' => '0.00',
            'para_birimi' => 'TRY',
        ]);

        $kalem = FaturaKalemi::withoutGlobalScopes()->create([
            'firma_id' => $firma->id,
            'fatura_id' => $fatura->id,
            'hizmet_mi' => true,
            'aciklama' => 'Servis',
            'miktar' => '1.0000',
            'birim_fiyat' => '100.00',
            'kdv_orani' => '20.00',
            'satir_indirim_tutari' => '0.00',
            'toplam' => '120.00',
            'satir_toplami' => '120.00',
        ]);

        $kalem->update(['miktar' => '2.0000', 'toplam' => '240.00', 'satir_toplami' => '240.00']);

        $this->assertDatabaseHas('denetim_kayitlari', [
            'olay' => 'fatura_kalemi.guncelle',
            'konu_tipi' => FaturaKalemi::class,
            'konu_id' => $kalem->id,
        ]);
    }

    public function test_audit_mesaji_okunabilir_etiket_uretir(): void
    {
        $etiket = app(AuditOlaySunumServisi::class)->etiket('reconcile.fix_basarili');
        $this->assertSame('Reconciliation düzeltmesi uygulandı', $etiket);
    }

    public function test_secret_alanlari_auditte_maskelenir(): void
    {
        $guvenli = app(AuditTrailServisi::class)->guvenliDizi([
            'provider' => 'paytr',
            'api_key' => 'SECRET',
            'nested' => ['token' => 'TOKEN-1'],
        ]);

        $this->assertSame('[MASKED]', $guvenli['api_key']);
        $this->assertSame('[MASKED]', $guvenli['nested']['token']);
    }

    public function test_ui_export_aksiyonu_yoksa_cli_export_notu_icin_kapsam_nettir(): void
    {
        $filamentPath = base_path('app/Filament');
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($filamentPath));
        $bulundu = false;

        foreach ($iter as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $icerik = file_get_contents($file->getPathname());
            if (is_string($icerik) && str_contains($icerik, 'ExportAction')) {
                $bulundu = true;
                break;
            }
        }

        $this->assertFalse($bulundu);
    }

    public function test_denetim_ekrani_kritik_ve_okunabilir_sunum_icerir(): void
    {
        $icerik = file_get_contents(base_path('app/Filament/Resources/DenetimKayidiKaynagi.php'));
        $this->assertIsString($icerik);
        $this->assertStringContainsString("TernaryFilter::make('kritik')", $icerik);
        $this->assertStringContainsString('app(AuditOlaySunumServisi::class)->etiket', $icerik);
        $this->assertStringContainsString('class_basename($state)', $icerik);
    }
}
