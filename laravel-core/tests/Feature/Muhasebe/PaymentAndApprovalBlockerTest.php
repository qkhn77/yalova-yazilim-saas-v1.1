<?php

namespace Tests\Feature\Muhasebe;

use App\Filament\Clusters\Muhasebe\Pages\OdemeOlusturSayfasi;
use App\Models\Firma;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Servisler\FaturaIslemServisi;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentAndApprovalBlockerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_supplier_payment_page_persists_invoice_linked_payment_and_domain_effects(): void
    {
        $firma = Firma::query()->create([
            'ad' => 'Payment blocker test',
            'kisa_ad' => 'PBT',
            'firma_kodu' => 'PBT-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
        $user = User::query()->create([
            'name' => 'Payment blocker test user',
            'email' => 'payment-blocker-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);
        $cari = Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'SUP-'.uniqid(),
            'ad' => 'Payment blocker supplier',
            'tur' => CariTuru::Tedarikci->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);
        $banka = BankaHesabi::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'BANK-'.uniqid(),
            'ad' => 'Payment blocker bank',
            'para_birimi' => 'TRY',
            'durum' => 'aktif',
        ]);
        $fatura = Fatura::query()->create([
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'tur' => FaturaTuru::Gelen->value,
            'durum' => FaturaDurumu::Taslak->value,
            'tarih' => now(),
            'ara_toplam' => '100',
            'kdv_toplam' => '20',
            'genel_toplam' => '120',
            'genel_indirim_tutari' => '0',
            'toplam_indirim' => '0',
            'odenecek_tutar' => '120',
            'odendi_tutari' => '0',
            'acik_tutar' => '120',
            'odeme_durumu' => 'odenmedi',
            'para_birimi' => 'TRY',
            'doviz_kuru' => '1',
        ]);
        FaturaKalemi::query()->create([
            'firma_id' => $firma->id,
            'fatura_id' => $fatura->id,
            'satir_no' => 1,
            'kalem_tipi' => 'hizmet_kalemi',
            'hizmet_mi' => true,
            'miktar' => '1',
            'birim_fiyat' => '100',
            'kdv_orani' => '20',
            'net_tutar' => '100',
            'kdv_tutari' => '20',
            'toplam' => '120',
            'satir_toplami' => '100',
            'satir_genel_toplam' => '120',
            'para_birimi' => 'TRY',
        ]);

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);
        app(FaturaIslemServisi::class)->faturayiOnayla($fatura);

        Livewire::test(OdemeOlusturSayfasi::class)
            ->set('data', [
                'kanal' => 'banka',
                'tarih' => now()->format('Y-m-d H:i:s'),
                'tutar' => '40',
                'aciklama' => 'Targeted payment regression',
                'cari_id' => $cari->id,
                'fatura_id' => $fatura->id,
                'isletme_proje_id' => null,
                'kaynak_para_birimi' => 'TRY',
                'hedef_para_birimi' => 'TRY',
                'doviz_kuru_turu' => 'otomatik',
                'doviz_kuru' => null,
                'hedef_tutar' => null,
                'kasa_hesap_id' => null,
                'banka_hesap_id' => $banka->id,
            ])
            ->call('kaydet');

        Livewire::test(OdemeOlusturSayfasi::class)
            ->set('data', [
                'kanal' => 'banka',
                'tarih' => now()->format('Y-m-d H:i:s'),
                'tutar' => '30',
                'aciklama' => 'Second partial payment regression',
                'cari_id' => $cari->id,
                'fatura_id' => $fatura->id,
                'isletme_proje_id' => null,
                'kaynak_para_birimi' => 'TRY',
                'hedef_para_birimi' => 'TRY',
                'doviz_kuru_turu' => 'otomatik',
                'doviz_kuru' => null,
                'hedef_tutar' => null,
                'kasa_hesap_id' => null,
                'banka_hesap_id' => $banka->id,
            ])
            ->call('kaydet');

        $this->assertDatabaseHas('finans_hareketleri', [
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'tur' => 'odeme',
            'tutar' => '40.00000000',
            'referans_turu' => 'fatura',
            'referans_id' => $fatura->id,
        ]);
        $this->assertDatabaseHas('banka_hareketleri', [
            'firma_id' => $firma->id,
            'banka_hesap_id' => $banka->id,
            'tutar' => '-40.00',
        ]);
        $this->assertDatabaseHas('finans_hareketleri', [
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'tur' => 'odeme',
            'tutar' => '30.00000000',
            'referans_turu' => 'fatura',
            'referans_id' => $fatura->id,
        ]);
        $this->assertDatabaseHas('cari_hareketleri', [
            'firma_id' => $firma->id,
            'cari_id' => $cari->id,
            'borc' => '0.00',
            'alacak' => '40.00',
        ]);

        $this->assertSame('70.00', number_format((float) $fatura->fresh()->odendi_tutari, 2, '.', ''));
        $this->assertSame('50.00', number_format((float) $fatura->fresh()->acik_tutar, 2, '.', ''));
        $this->assertSame('kismi_odendi', $fatura->fresh()->odeme_durumu);
        $this->assertSame(
            70.0,
            (float) FinansHareketi::query()
                ->where('firma_id', $firma->id)
                ->where('cari_id', $cari->id)
                ->where('tur', 'odeme')
                ->where('referans_turu', 'fatura')
                ->where('referans_id', $fatura->id)
                ->sum('tutar'),
        );
    }
}
