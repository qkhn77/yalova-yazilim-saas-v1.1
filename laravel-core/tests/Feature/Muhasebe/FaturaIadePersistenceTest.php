<?php

namespace Tests\Feature\Muhasebe;

use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateGelenFatura;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateGelenIadeFaturasi;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateGidenFatura;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateGidenIadeFaturasi;
use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaSinifi;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class FaturaIadePersistenceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_satis_iadesi_iki_bagimsiz_kismi_iade_olarak_kaydedilir(): void
    {
        [$firma, $cari, $depo, $stok] = $this->kurulum(CariTuru::Musteri);
        $this->actingAs($this->kullanici());
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        foreach ([1, 2] as $run) {
            $kaynak = $this->faturaOlustur(CreateGidenFatura::class, $firma, $cari, $depo, $stok, 4);
            $this->assertSame(FaturaDurumu::Onayli, $kaynak->fresh()->durum);

            $before = Fatura::withoutGlobalScopes()->where('bagli_fatura_id', $kaynak->id)->count();
            $kaynakKalem = $kaynak->kalemler()->firstOrFail();
            $component = Livewire::withQueryParams(['kaynak_fatura_id' => $kaynak->id])
                ->test(CreateGidenIadeFaturasi::class)
                ->fillForm($this->iadeData($kaynak, $kaynakKalem, FaturaTuru::SatisIadesi))
                ->set('data.kalemler.0.miktar', 1);
            $component->assertSet('data.kalemler.0.kaynak_fatura_kalemi_id', $kaynakKalem->id);
            $component->call('create');
            if ($component->errors()->isNotEmpty()) {
                $this->fail('Sales return validation: '.$component->errors()->toJson());
            }

            $iade = Fatura::withoutGlobalScopes()
                ->where('bagli_fatura_id', $kaynak->id)
                ->latest('id')
                ->firstOrFail();
            $this->assertSame($before + 1, Fatura::withoutGlobalScopes()->where('bagli_fatura_id', $kaynak->id)->count());
            $this->assertSame(FaturaTuru::SatisIadesi, $iade->tur);
            $this->assertSame(FaturaDurumu::Onayli, $iade->durum);
            $this->assertSame('1.00000000', $iade->kalemler()->value('miktar'));
            $this->assertSame(1, StokHareketi::withoutGlobalScopes()->where('belge_id', $iade->id)->count());
            $this->assertSame($cari->id, (int) $iade->cari_id);
        }
    }

    public function test_alis_iadesi_gercek_create_akisinda_kaydedilir(): void
    {
        [$firma, $cari, $depo, $stok] = $this->kurulum(CariTuru::Tedarikci);
        $this->actingAs($this->kullanici());
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);
        $kaynak = $this->faturaOlustur(CreateGelenFatura::class, $firma, $cari, $depo, $stok, 4);
        $kaynakKalem = $kaynak->kalemler()->firstOrFail();

        $component = Livewire::withQueryParams(['kaynak_fatura_id' => $kaynak->id])
            ->test(CreateGelenIadeFaturasi::class)
            ->fillForm($this->iadeData($kaynak, $kaynakKalem, FaturaTuru::AlisIadesi))
            ->set('data.kalemler.0.miktar', 1);
        $component->assertSet('data.kalemler.0.kaynak_fatura_kalemi_id', $kaynakKalem->id);
        $component->call('create');
        if ($component->errors()->isNotEmpty()) {
            $this->fail('Purchase return validation: '.$component->errors()->toJson());
        }

        $iade = Fatura::withoutGlobalScopes()->where('bagli_fatura_id', $kaynak->id)->latest('id')->firstOrFail();
        $this->assertSame(FaturaTuru::AlisIadesi, $iade->tur);
        $this->assertSame(FaturaDurumu::Onayli, $iade->durum);
        $this->assertSame('1.00000000', $iade->kalemler()->value('miktar'));
        $this->assertSame(1, StokHareketi::withoutGlobalScopes()->where('belge_id', $iade->id)->count());
    }

    private function kullanici(): User
    {
        return User::create([
            'name' => 'FAZ 4.6.12 QA',
            'email' => 'phase4612-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);
    }

    /** @return array{0:Firma,1:Cari,2:Depo,3:StokKarti} */
    private function kurulum(CariTuru $cariTuru): array
    {
        $firma = Firma::create([
            'ad' => 'FAZ 4.6.12 Firma',
            'kisa_ad' => 'F4612',
            'firma_kodu' => 'F4612-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
        $cari = Cari::create([
            'firma_id' => $firma->id,
            'kod' => 'C-4612-'.uniqid(),
            'ad' => 'FAZ 4.6.12 Cari',
            'tur' => $cariTuru->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);
        $depo = Depo::create([
            'firma_id' => $firma->id,
            'kod' => 'D-4612-'.uniqid(),
            'ad' => 'FAZ 4.6.12 Depo',
            'aktif_mi' => true,
        ]);
        $stok = StokKarti::create([
            'firma_id' => $firma->id,
            'kod' => 'S-4612-'.uniqid(),
            'ad' => 'FAZ 4.6.12 Stok',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'birim' => 'AD',
            'para_birimi' => 'TRY',
            'kdv_orani' => 0,
            'stok_takip' => true,
            'stok_miktari' => 20,
            'depo_id' => $depo->id,
        ]);

        return [$firma, $cari, $depo, $stok];
    }

    private function faturaOlustur(string $page, Firma $firma, Cari $cari, Depo $depo, StokKarti $stok, int $miktar): Fatura
    {
        Livewire::test($page)
            ->set('data', [
                'firma_id' => $firma->id,
                'cari_id' => $cari->id,
                'tur' => $page === CreateGelenFatura::class ? FaturaTuru::Gelen->value : FaturaTuru::Giden->value,
                'durum' => FaturaDurumu::Onayli->value,
                'tarih' => now()->format('Y-m-d H:i:s'),
                'para_birimi' => 'TRY',
                'doviz_kuru' => 1,
                'fatura_sinifi' => $page === CreateGelenFatura::class ? FaturaSinifi::StokAlisi->value : null,
                'kdv_dahil_fiyatlandirma_mi' => false,
                'tevkifat_orani' => 0,
                'ara_toplam' => $miktar * 150,
                'kdv_toplam' => 0,
                'genel_toplam' => $miktar * 150,
                'odenecek_tutar' => $miktar * 150,
                'odendi_tutari' => 0,
                'acik_tutar' => $miktar * 150,
                'toplam_indirim' => 0,
                'kalemler' => [[
                    'satir_no' => 1,
                    'sira_no' => 1,
                    'kalem_tipi' => 'stok_kalemi',
                    'stok_id' => $stok->id,
                    'depo_id' => $depo->id,
                    'hizmet_mi' => false,
                    'birim' => 'AD',
                    'miktar' => $miktar,
                    'birim_fiyat' => 150,
                    'indirim_orani' => 0,
                    'kdv_orani' => 0,
                    'para_birimi' => 'TRY',
                ]],
            ])
            ->call('create')
            ->assertHasNoErrors();

        return Fatura::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function iadeSatiri(FaturaKalemi $kaynakKalem, int $miktar): array
    {
        return [
            'kaynak_fatura_kalemi_id' => $kaynakKalem->id,
            'kalem_tipi' => 'stok_kalemi',
            'hizmet_mi' => false,
            'stok_id' => $kaynakKalem->stok_id,
            'depo_id' => $kaynakKalem->depo_id,
            'miktar' => $miktar,
            'birim' => $kaynakKalem->birim,
            'birim_fiyat' => $kaynakKalem->birim_fiyat,
            'indirim_orani' => 0,
            'kdv_orani' => $kaynakKalem->kdv_orani,
            'sira_no' => 1,
            'satir_no' => 1,
            'para_birimi' => 'TRY',
        ];
    }

    /** @return array<string, mixed> */
    private function iadeData(Fatura $kaynak, FaturaKalemi $kaynakKalem, FaturaTuru $tur): array
    {
        return [
            'firma_id' => $kaynak->firma_id,
            'cari_id' => $kaynak->cari_id,
            'bagli_fatura_id' => $kaynak->id,
            'tur' => $tur->value,
            'durum' => FaturaDurumu::Onayli->value,
            'tarih' => now()->format('Y-m-d H:i:s'),
            'para_birimi' => 'TRY',
            'doviz_kuru' => 1,
            'kdv_dahil_fiyatlandirma_mi' => false,
            'tevkifat_orani' => 0,
            'ara_toplam' => 150,
            'kdv_toplam' => 0,
            'genel_toplam' => 150,
            'odenecek_tutar' => 150,
            'odendi_tutari' => 0,
            'acik_tutar' => 150,
            'toplam_indirim' => 0,
            'kalemler' => [$this->iadeSatiri($kaynakKalem, 1)],
        ];
    }
}
