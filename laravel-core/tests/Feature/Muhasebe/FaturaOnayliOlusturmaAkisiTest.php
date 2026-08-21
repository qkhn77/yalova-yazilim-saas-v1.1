<?php

namespace Tests\Feature\Muhasebe;

use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateBekleyenFatura;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateFatura;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateGelenFatura;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateGelenIadeFaturasi;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateGidenFatura;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateGidenIadeFaturasi;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateGiderFaturasi;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateIptalFatura;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages\CreateProformaFatura;
use App\Models\Firma;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\StokKarti;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Servisler\StokOlcuBakiyeServisi;
use App\Services\TenantContextService;
use Database\Seeders\MuhasebeOlcuBirimleriSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FaturaOnayliOlusturmaAkisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_tum_fatura_olusturma_sayfalari_ortak_guvenli_olusturma_akisini_kullanir(): void
    {
        foreach ([
            CreateGidenFatura::class,
            CreateGelenFatura::class,
            CreateGiderFaturasi::class,
            CreateBekleyenFatura::class,
            CreateGidenIadeFaturasi::class,
            CreateGelenIadeFaturasi::class,
            CreateProformaFatura::class,
            CreateIptalFatura::class,
        ] as $page) {
            $this->assertTrue(is_subclass_of($page, CreateFatura::class), $page.' ortak CreateFatura akışını kullanmalıdır.');
        }
    }

    public function test_onayli_giden_fatura_olusturulurken_kalem_onaydan_once_kaydedilir(): void
    {
        $firma = Firma::query()->create([
            'ad' => 'Test Firma',
            'kisa_ad' => 'TF',
            'firma_kodu' => 'TF-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
        $user = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'sa-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);
        $cari = Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'C-001',
            'ad' => 'Euro Cari',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'EUR',
        ]);
        $stok = StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'STK-001',
            'ad' => 'Test Stok',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'birim' => 'AD',
            'para_birimi' => 'TRY',
            'kdv_orani' => 0,
            'stok_takip' => true,
            'stok_miktari' => 10,
        ]);

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        Livewire::test(CreateGidenFatura::class)
            ->set('data', [
                'firma_id' => $firma->id,
                'cari_id' => $cari->id,
                'tur' => FaturaTuru::Giden->value,
                'durum' => FaturaDurumu::Onayli->value,
                'tarih' => now()->format('Y-m-d H:i:s'),
                'para_birimi' => 'TRY',
                'doviz_kuru' => 1,
                'kdv_dahil_fiyatlandirma_mi' => false,
                'tevkifat_orani' => 0,
                'ara_toplam' => 100,
                'kdv_toplam' => 0,
                'genel_toplam' => 100,
                'odenecek_tutar' => 100,
                'acik_tutar' => 100,
                'kalemler' => [[
                    'satir_no' => 1,
                    'sira_no' => 1,
                    'kalem_tipi' => 'stok_kalemi',
                    'stok_id' => $stok->id,
                    'hizmet_mi' => false,
                    'aciklama' => 'Test stok satiri',
                    'birim' => 'AD',
                    'miktar' => 1,
                    'birim_fiyat' => 100,
                    'indirim_orani' => 0,
                    'indirim_tutari' => 0,
                    'kdv_orani' => 0,
                    'para_birimi' => 'TRY',
                ]],
            ])
            ->call('create')
            ->assertHasNoErrors();

        $fatura = Fatura::withoutGlobalScopes()->sole();

        $this->assertSame(1, FaturaKalemi::withoutGlobalScopes()->where('fatura_id', $fatura->id)->count());
        $this->assertSame(FaturaDurumu::Onayli, $fatura->durum);
        $this->assertSame('100.00000000', $fatura->genel_toplam);
    }

    public function test_olculu_stokta_olcu_dagilimi_olmadan_onayli_fatura_olusturulamaz(): void
    {
        $firma = Firma::query()->create([
            'ad' => 'Ölçülü Test Firma',
            'kisa_ad' => 'ÖTF',
            'firma_kodu' => 'OTF-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
        $user = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'olcu-sa-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);
        $cari = Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'C-OLCU',
            'ad' => 'Ölçülü Cari',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);
        $this->seed(MuhasebeOlcuBirimleriSeeder::class);
        $anaBirim = Birim::withoutGlobalScopes()->where('kod', 'MTK')->firstOrFail();
        $adetBirimi = Birim::withoutGlobalScopes()->where('kod', 'AD')->firstOrFail();
        $stok = StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'OLCU-001',
            'ad' => 'Ölçülü Test Stok',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'birim' => 'MTK',
            'para_birimi' => 'TRY',
            'kdv_orani' => 0,
            'stok_takip' => true,
            'stok_miktari' => 10,
            'olculu_takip_turu' => 'alan',
            'ana_birim_id' => $anaBirim->id,
            'ikincil_birim_id' => $adetBirimi->id,
        ]);

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        Livewire::test(CreateGidenFatura::class)
            ->set('data', [
                'firma_id' => $firma->id,
                'cari_id' => $cari->id,
                'tur' => FaturaTuru::Giden->value,
                'durum' => FaturaDurumu::Onayli->value,
                'tarih' => now()->format('Y-m-d H:i:s'),
                'para_birimi' => 'TRY',
                'doviz_kuru' => 1,
                'kdv_dahil_fiyatlandirma_mi' => false,
                'tevkifat_orani' => 0,
                'ara_toplam' => 1000,
                'kdv_toplam' => 0,
                'genel_toplam' => 1000,
                'odenecek_tutar' => 1000,
                'acik_tutar' => 1000,
                'kalemler' => [[
                    'satir_no' => 1,
                    'sira_no' => 1,
                    'kalem_tipi' => 'stok_kalemi',
                    'stok_id' => $stok->id,
                    'birim' => 'MTK',
                    'miktar' => 1,
                    'birim_fiyat' => 100,
                    'kdv_orani' => 0,
                    'fiyat_birimi_id' => $anaBirim->id,
                    'olcu_dagilimlari' => [],
                    'para_birimi' => 'TRY',
                ]],
            ])
            ->call('create')
            ->assertHasErrors(['data.kalemler.0.olcu_dagilimlari']);

        $this->assertSame(0, Fatura::withoutGlobalScopes()->count());
    }

    public function test_olculu_stok_olcu_dagilimiyle_onayli_fatura_olusturabilir(): void
    {
        $firma = Firma::query()->create([
            'ad' => 'Ölçülü Başarılı Firma',
            'kisa_ad' => 'ÖBF',
            'firma_kodu' => 'OBF-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);
        $user = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'olcu-basarili-'.uniqid().'@test.local',
            'password' => bcrypt('x'),
            'super_admin_mi' => true,
        ]);
        $cari = Cari::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'C-OLCU-B',
            'ad' => 'Ölçülü Başarılı Cari',
            'tur' => CariTuru::Musteri->value,
            'durum' => CariDurumu::Aktif->value,
            'para_birimi' => 'TRY',
        ]);
        $depo = Depo::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'D-OLCU',
            'ad' => 'Ölçülü Depo',
            'aktif_mi' => true,
        ]);
        $this->seed(MuhasebeOlcuBirimleriSeeder::class);
        $anaBirim = Birim::withoutGlobalScopes()->where('kod', 'MTK')->firstOrFail();
        $adetBirimi = Birim::withoutGlobalScopes()->where('kod', 'AD')->firstOrFail();
        $stok = StokKarti::query()->create([
            'firma_id' => $firma->id,
            'kod' => 'OLCU-B-001',
            'ad' => 'Ölçülü Başarılı Stok',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'birim' => 'MTK',
            'para_birimi' => 'TRY',
            'kdv_orani' => 0,
            'stok_takip' => true,
            'stok_miktari' => 4,
            'depo_id' => $depo->id,
            'olculu_takip_turu' => 'alan',
            'ana_birim_id' => $anaBirim->id,
            'ikincil_birim_id' => $adetBirimi->id,
        ]);
        $olcuServisi = app(StokOlcuBakiyeServisi::class);
        $olcu = $olcuServisi->olcuOlustur($firma->id, $stok, [
            'kod' => '200X200',
            'ad' => '200x200',
            'olcu_birimi' => 'cm',
            'en' => '200',
            'boy' => '200',
        ]);
        $bakiye = $olcuServisi->bakiyeBulVeyaOlustur($firma->id, $stok, $olcu, $depo);
        $olcuServisi->giris($bakiye, anaMiktar: '4');

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        Livewire::test(CreateGidenFatura::class)
            ->set('data', [
                'firma_id' => $firma->id,
                'cari_id' => $cari->id,
                'tur' => FaturaTuru::Giden->value,
                'durum' => FaturaDurumu::Onayli->value,
                'tarih' => now()->format('Y-m-d H:i:s'),
                'para_birimi' => 'TRY',
                'doviz_kuru' => 1,
                'kdv_dahil_fiyatlandirma_mi' => false,
                'tevkifat_orani' => 0,
                'ara_toplam' => 1000,
                'kdv_toplam' => 0,
                'genel_toplam' => 1000,
                'odenecek_tutar' => 1000,
                'acik_tutar' => 1000,
                'kalemler' => [[
                    'satir_no' => 1,
                    'sira_no' => 1,
                    'kalem_tipi' => 'stok_kalemi',
                    'stok_id' => $stok->id,
                    'birim' => 'MTK',
                    'miktar' => 1,
                    'birim_fiyat' => 1000,
                    'kdv_orani' => 0,
                    'fiyat_birimi_id' => $anaBirim->id,
                    'olcu_dagilimlari' => [[
                        'stok_olcusu_id' => $olcu->id,
                        'stok_olcu_bakiyesi_id' => $bakiye->id,
                        'depo_id' => $depo->id,
                        'islem_birimi_id' => $anaBirim->id,
                        'girilen_miktar' => 1,
                    ]],
                    'para_birimi' => 'TRY',
                ]],
            ])
            ->call('create')
            ->assertHasNoErrors();

        $fatura = Fatura::withoutGlobalScopes()->sole();
        $kalem = FaturaKalemi::withoutGlobalScopes()->where('fatura_id', $fatura->id)->sole();

        $this->assertSame(FaturaDurumu::Onayli, $fatura->durum);
        $this->assertSame('1.00000000', $kalem->ana_miktar);
        $this->assertSame(1, $kalem->olcuDagilimlari()->count());
    }
}
