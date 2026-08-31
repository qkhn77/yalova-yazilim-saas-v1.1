<?php

namespace Tests\Feature\Muhasebe;

use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Livewire\Muhasebe\FaturaDetaySekmesi;
use App\Models\Firma;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokHareketiOlcuDagilimi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokOlcuBakiyesi;
use App\Models\Muhasebe\StokOlcusu;
use App\Models\User;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\FaturaIslemServisi;
use App\Muhasebe\Servisler\FaturaOlcuKalemiServisi;
use App\Muhasebe\Servisler\StokOlcuBakiyeServisi;
use App\Services\FirmaAyarDeposu;
use Database\Seeders\MuhasebeOlcuBirimleriSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class FaturaOlcuEntegrasyonTest extends TestCase
{
    use DatabaseTransactions;

    public function test_taslak_olcu_dagilimi_saklanir_ve_sunucuda_hesaplanir(): void
    {
        [$firma, $cari, $depo, $stok, $olcu, $bakiye, $fatura, $kalem] = $this->kurulum();
        app(FaturaOlcuKalemiServisi::class)->dagilimlariSakla($kalem, [[
            'stok_olcusu_id' => $olcu->id, 'stok_olcu_bakiyesi_id' => $bakiye->id, 'depo_id' => $depo->id,
            'islem_birimi_id' => Birim::withoutGlobalScopes()->where('kod', 'MTK')->value('id'), 'girilen_miktar' => '1',
        ]], true);

        $this->assertSame('1.00000000', $kalem->refresh()->ana_miktar);
        $this->assertSame('0.25000000', $kalem->adet_esdegeri);
        $this->assertDatabaseHas('fatura_kalemi_olcu_dagilimlari', ['fatura_kalemi_id' => $kalem->id, 'ana_miktar' => '1.00000000']);
    }



    public function test_olculu_satis_onayinda_hareket_ana_miktarla_olur_ve_bakiye_azalir(): void
    {
        [$firma, $cari, $depo, $stok, $olcu, $bakiye, $fatura, $kalem] = $this->kurulum();
        app(FaturaOlcuKalemiServisi::class)->dagilimlariSakla($kalem, [[
            'stok_olcusu_id' => $olcu->id, 'stok_olcu_bakiyesi_id' => $bakiye->id, 'depo_id' => $depo->id,
            'islem_birimi_id' => Birim::withoutGlobalScopes()->where('kod', 'MTK')->value('id'), 'girilen_miktar' => '1',
        ]], true);
        app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());

        $this->assertSame('3.00000000', $bakiye->refresh()->ana_miktar);
        $this->assertSame('1.00000000', StokHareketi::query()->where('belge_id', $fatura->id)->value('miktar'));
    }

    public function test_olculu_satis_dagilimsiz_onayda_rollback_olur(): void
    {
        [$firma, $cari, $depo, $stok, $olcu, $bakiye, $fatura, $kalem] = $this->kurulum();
        $this->expectException(IsKuraliIstisnasi::class);
        $this->expectExceptionMessage('Ölçülü stok kaleminde en az bir ölçü dağılımı seçilmelidir');
        app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());
        $this->assertSame(FaturaDurumu::Taslak, $fatura->fresh()->durum);
    }

    public function test_olculu_satis_iptalinde_ayni_bakiye_geri_girer(): void
    {
        [$firma, $cari, $depo, $stok, $olcu, $bakiye, $fatura, $kalem] = $this->kurulum();
        app(FaturaOlcuKalemiServisi::class)->dagilimlariSakla($kalem, [['stok_olcusu_id' => $olcu->id, 'stok_olcu_bakiyesi_id' => $bakiye->id, 'depo_id' => $depo->id, 'islem_birimi_id' => Birim::withoutGlobalScopes()->where('kod', 'MTK')->value('id'), 'girilen_miktar' => '1']], true);
        app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());
        app(FaturaIslemServisi::class)->faturayiIptalEt($fatura->fresh());

        $this->assertSame('4.00000000', $bakiye->refresh()->ana_miktar);
        $this->assertSame(FaturaDurumu::Iptal, $fatura->fresh()->durum);
    }

    public function test_olculu_satis_iadesi_kaynak_fatura_olmadan_onaylanamaz(): void
    {
        [$firma, $cari, $depo, $stok, $olcu, $bakiye, $kaynak, $kaynakKalem] = $this->kurulum();
        $iade = Fatura::create([
            'firma_id' => $firma->id, 'cari_id' => $cari->id, 'tur' => FaturaTuru::SatisIadesi->value,
            'durum' => FaturaDurumu::Taslak->value, 'tarih' => now(), 'ara_toplam' => '1000',
            'kdv_toplam' => '0', 'genel_toplam' => '1000', 'toplam_indirim' => '0',
            'odenecek_tutar' => '1000', 'odendi_tutari' => '0', 'acik_tutar' => '1000',
            'para_birimi' => 'TRY', 'doviz_kuru' => '1',
        ]);
        FaturaKalemi::create([
            'firma_id' => $firma->id, 'fatura_id' => $iade->id, 'satir_no' => 1, 'stok_id' => $stok->id,
            'depo_id' => $depo->id, 'miktar' => '1', 'ana_miktar' => '1', 'birim' => 'MTK',
            'birim_fiyat' => '1000', 'kdv_orani' => '0', 'net_tutar' => '1000', 'toplam' => '1000',
            'satir_toplami' => '1000', 'satir_genel_toplam' => '1000', 'para_birimi' => 'TRY',
        ]);

        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
    }

    public function test_olculu_satis_tam_iadesi_ayni_bakiye_ye_girer(): void
    {
        [$kaynak, $iade, $bakiye] = $this->tamIadeKur();
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        $this->assertSame('4.00000000', $bakiye->refresh()->ana_miktar);
        $this->assertSame('satis_iadesi', (string) StokHareketi::query()->where('belge_id', $iade->id)->value('islem_turu')->value);
    }

    public function test_tam_iadede_kaynak_kalem_ve_dagilim_baglantisi_korunur(): void
    {
        [$kaynak, $iade] = $this->tamIadeKur();
        $kalem = $iade->kalemler()->firstOrFail();
        $dagilim = $kalem->olcuDagilimlari()->firstOrFail();
        $this->assertSame($kaynak->kalemler()->first()->id, $kalem->kaynak_fatura_kalemi_id);
        $this->assertSame($kaynak->kalemler()->first()->olcuDagilimlari()->first()->id, $dagilim->kaynak_olcu_dagilimi_id);
    }

    public function test_farkli_olcu_payload_iade_onayinda_reddedilir(): void
    {
        [$kaynak, $iade, $bakiye] = $this->tamIadeKur();
        $farkli = app(StokOlcuBakiyeServisi::class)->olcuOlustur($kaynak->firma_id, $kaynak->kalemler()->first()->stokKarti, ['kod' => '100X100', 'ad' => '100x100', 'olcu_birimi' => 'cm', 'en' => '100', 'boy' => '100']);
        $iade->kalemler()->first()->olcuDagilimlari()->update(['stok_olcusu_id' => $farkli->id]);
        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        $this->assertSame('3.00000000', $bakiye->refresh()->ana_miktar);
    }

    public function test_baska_cari_kaynagi_iade_onayinda_reddedilir(): void
    {
        [$kaynak, $iade] = $this->tamIadeKur();
        $baskaCari = Cari::create(['firma_id' => $kaynak->firma_id, 'kod' => 'C-2', 'ad' => 'Başka Cari', 'tur' => CariTuru::Musteri->value, 'durum' => CariDurumu::Aktif->value]);
        $iade->update(['cari_id' => $baskaCari->id]);
        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
    }

    public function test_baska_tenant_kaynagi_iade_onayinda_reddedilir(): void
    {
        [$kaynak, $iade] = $this->tamIadeKur();
        $baskaFirma = Firma::create(['ad' => 'Başka Firma', 'kisa_ad' => 'BF', 'firma_kodu' => 'BF-'.uniqid(), 'durum' => Firma::DURUM_AKTIF, 'onaylandi_mi' => true]);
        $baskaKaynak = Fatura::create(['firma_id' => $baskaFirma->id, 'cari_id' => null, 'tur' => FaturaTuru::Giden->value, 'durum' => FaturaDurumu::Onayli->value, 'tarih' => now()]);
        $iade->update(['bagli_fatura_id' => $baskaKaynak->id]);
        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
    }

    public function test_taslak_kaynak_fatura_iade_onayinda_reddedilir(): void
    {
        [$firma, $cari, $depo, $stok, $olcu, $bakiye, $kaynak, $kaynakKalem] = $this->kurulum();
        app(FaturaOlcuKalemiServisi::class)->dagilimlariSakla($kaynakKalem, [['stok_olcusu_id' => $olcu->id, 'stok_olcu_bakiyesi_id' => $bakiye->id, 'depo_id' => $depo->id, 'islem_birimi_id' => Birim::withoutGlobalScopes()->where('kod', 'MTK')->value('id'), 'girilen_miktar' => '1']], true);
        $iade = $this->iadeKalemiOlustur($kaynak, $kaynakKalem, $cari, $depo, $stok, $olcu, $bakiye);
        $iade->kalemler()->first()->update(['kaynak_fatura_kalemi_id' => $kaynakKalem->id]);
        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
    }

    public function test_ayni_kaynak_ikinci_tam_iadede_reddedilir(): void
    {
        [$kaynak, $iade] = $this->tamIadeKur();
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        [, $ikinci] = $this->tamIadeKur($kaynak);
        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaIslemServisi::class)->faturayiOnayla($ikinci->fresh());
    }

    public function test_tam_satis_iadesi_iptalinde_bakiye_geri_doner(): void
    {
        [$kaynak, $iade, $bakiye] = $this->tamIadeKur();
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        app(FaturaIslemServisi::class)->faturayiIptalEt($iade->fresh());
        $this->assertSame('3.00000000', $bakiye->refresh()->ana_miktar);
        $this->assertSame(FaturaDurumu::Iptal, $iade->fresh()->durum);
    }

    public function test_iade_cift_onayda_bakiye_tek_keze_artar(): void
    {
        [$kaynak, $iade, $bakiye] = $this->tamIadeKur();
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        $this->assertSame('4.00000000', $bakiye->refresh()->ana_miktar);
        $this->assertSame(1, StokHareketi::query()->where('belge_id', $iade->id)->count());
    }

    public function test_iade_dagilim_hatasi_tam_rollback_uygular(): void
    {
        [$kaynak, $iade, $bakiye] = $this->tamIadeKur();
        $iadeDagilimId = $iade->kalemler()->first()->olcuDagilimlari()->firstOrFail()->id;
        $iade->kalemler()->first()->olcuDagilimlari()->update(['kaynak_olcu_dagilimi_id' => $iadeDagilimId]);
        $this->expectException(IsKuraliIstisnasi::class);
        try {
            app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        } finally {
            $this->assertSame('3.00000000', $bakiye->refresh()->ana_miktar);
            $this->assertSame(0, StokHareketi::query()->where('belge_id', $iade->id)->count());
        }
    }

    public function test_iptal_edilen_iade_sonrasi_kaynak_yeniden_tam_iade_edilebilir(): void
    {
        [$kaynak, $iade, $bakiye] = $this->tamIadeKur();
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        app(FaturaIslemServisi::class)->faturayiIptalEt($iade->fresh());
        [, $yeniden] = $this->tamIadeKur($kaynak);
        app(FaturaIslemServisi::class)->faturayiOnayla($yeniden->fresh());
        $this->assertSame('4.00000000', $bakiye->refresh()->ana_miktar);
        $this->assertSame(FaturaDurumu::Onayli, $yeniden->fresh()->durum);
    }

    public function test_ikinci_iade_iptali_bakiyeyi_degistirmez(): void
    {
        [$kaynak, $iade, $bakiye] = $this->tamIadeKur();
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        app(FaturaIslemServisi::class)->faturayiIptalEt($iade->fresh());
        $once = $bakiye->refresh()->ana_miktar;
        app(FaturaIslemServisi::class)->faturayiIptalEt($iade->fresh());
        $this->assertSame($once, $bakiye->refresh()->ana_miktar);
        $this->assertSame(FaturaDurumu::Iptal, $iade->fresh()->durum);
    }

    public function test_iptal_sonrasi_kaynak_baglantilari_korunur(): void
    {
        [$kaynak, $iade] = $this->tamIadeKur();
        $kalem = $iade->kalemler()->firstOrFail();
        $kaynakKalemId = (int) $kalem->kaynak_fatura_kalemi_id;
        $kaynakDagilimId = (int) $kalem->olcuDagilimlari()->firstOrFail()->kaynak_olcu_dagilimi_id;
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        app(FaturaIslemServisi::class)->faturayiIptalEt($iade->fresh());
        $this->assertSame($kaynakKalemId, (int) $kalem->refresh()->kaynak_fatura_kalemi_id);
        $this->assertSame($kaynakDagilimId, (int) $kalem->olcuDagilimlari()->firstOrFail()->kaynak_olcu_dagilimi_id);
        $this->assertSame($kaynak->id, (int) $iade->fresh()->bagli_fatura_id);
    }

    public function test_fk_kaynak_dagilim_iliski_si_veritabaninda_korunur(): void
    {
        [$kaynak, $iade] = $this->tamIadeKur();
        $dagilim = $iade->kalemler()->firstOrFail()->olcuDagilimlari()->firstOrFail();
        $this->assertSame((int) $kaynak->kalemler()->firstOrFail()->olcuDagilimlari()->firstOrFail()->id, (int) $dagilim->kaynakOlcuDagilimi()->value('id'));
    }

    public function test_iade_hareketi_olcu_dagilimi_bakiye_ile_eslesir(): void
    {
        [$kaynak, $iade] = $this->tamIadeKur();
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        $kalemDagilim = $iade->kalemler()->firstOrFail()->olcuDagilimlari()->firstOrFail();
        $hareket = StokHareketi::query()->where('belge_id', $iade->id)->firstOrFail();
        $hareketDagilim = StokHareketiOlcuDagilimi::query()->where('stok_hareketi_id', $hareket->id)->firstOrFail();
        $this->assertSame((int) $kalemDagilim->stok_olcu_bakiyesi_id, (int) $hareketDagilim->stok_olcu_bakiyesi_id);
        $this->assertSame((string) $kalemDagilim->ana_miktar, (string) $hareketDagilim->ana_miktar);
    }

    public function test_olculu_alis_tam_iadesi_stoktan_cikar(): void
    {
        [$kaynak, $iade, $bakiye] = $this->tamAlisIadeKur();
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        $this->assertSame('4.00000000', $bakiye->refresh()->ana_miktar);
        $this->assertSame(FaturaDurumu::Onayli, $iade->fresh()->durum);
    }

    public function test_olculu_alis_iadesi_ayni_olcu_bakiyesinden_cikar(): void
    {
        [$kaynak, $iade, $bakiye] = $this->tamAlisIadeKur();
        $kaynakBakiyeId = $kaynak->kalemler()->firstOrFail()->olcuDagilimlari()->firstOrFail()->stok_olcu_bakiyesi_id;
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        $this->assertSame((int) $kaynakBakiyeId, (int) $iade->kalemler()->firstOrFail()->olcuDagilimlari()->firstOrFail()->stok_olcu_bakiyesi_id);
        $this->assertSame('4.00000000', $bakiye->refresh()->ana_miktar);
    }

    public function test_olculu_alis_iadesi_ayni_depodan_cikar(): void
    {
        [$kaynak, $iade] = $this->tamAlisIadeKur();
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        $depoId = $iade->kalemler()->firstOrFail()->depo_id;
        $this->assertSame((int) $depoId, (int) StokHareketi::query()->where('belge_id', $iade->id)->value('depo_id'));
    }

    public function test_olculu_alis_iadesi_yetersiz_guncel_bakiyeyi_reddeder(): void
    {
        [$kaynak, $iade, $bakiye] = $this->tamAlisIadeKur();
        $bakiye->update(['ana_miktar' => '0', 'adet_esdegeri' => '0']);
        $this->expectException(\Throwable::class);
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        $this->assertSame(FaturaDurumu::Taslak, $iade->fresh()->durum);
    }

    public function test_olculu_alis_iadesi_farkli_dagilim_payloadini_reddeder(): void
    {
        [$kaynak, $iade] = $this->tamAlisIadeKur();
        $id = $iade->kalemler()->firstOrFail()->olcuDagilimlari()->firstOrFail()->id;
        $iade->kalemler()->first()->olcuDagilimlari()->update(['kaynak_olcu_dagilimi_id' => $id]);
        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
    }

    public function test_olculu_alis_iadesi_farkli_depo_payloadini_reddeder(): void
    {
        [$kaynak, $iade] = $this->tamAlisIadeKur();
        $depo = Depo::create(['firma_id' => $kaynak->firma_id, 'kod' => 'D2', 'ad' => 'Başka Depo', 'aktif_mi' => true]);
        $iade->kalemler()->first()->olcuDagilimlari()->update(['depo_id' => $depo->id]);
        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
    }

    public function test_olculu_alis_iadesi_baska_tenant_kaynagini_reddeder(): void
    {
        [$kaynak, $iade] = $this->tamAlisIadeKur();
        $firma = Firma::create(['ad' => 'Başka Alış Firma', 'kisa_ad' => 'BAF', 'firma_kodu' => 'BAF-'.uniqid(), 'durum' => Firma::DURUM_AKTIF, 'onaylandi_mi' => true]);
        $baska = Fatura::create(['firma_id' => $firma->id, 'tur' => FaturaTuru::Gelen->value, 'durum' => FaturaDurumu::Onayli->value, 'tarih' => now()]);
        $iade->update(['bagli_fatura_id' => $baska->id]);
        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
    }

    public function test_olculu_alis_iadesi_baska_cari_kaynagini_reddeder(): void
    {
        [$kaynak, $iade] = $this->tamAlisIadeKur();
        $cari = Cari::create(['firma_id' => $kaynak->firma_id, 'kod' => 'C-2', 'ad' => 'Başka Alış Cari', 'tur' => CariTuru::Tedarikci->value, 'durum' => CariDurumu::Aktif->value]);
        $iade->update(['cari_id' => $cari->id]);
        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
    }

    public function test_olculu_alis_iadesi_taslak_kaynagi_reddeder(): void
    {
        [$firma, $cari, $depo, $stok, $olcu, $bakiye, $kaynak, $kaynakKalem] = $this->kurulum();
        $kaynak->update(['tur' => FaturaTuru::Gelen->value]);
        app(FaturaOlcuKalemiServisi::class)->dagilimlariSakla($kaynakKalem, [['stok_olcusu_id' => $olcu->id, 'stok_olcu_bakiyesi_id' => $bakiye->id, 'depo_id' => $depo->id, 'islem_birimi_id' => Birim::withoutGlobalScopes()->where('kod', 'MTK')->value('id'), 'girilen_miktar' => '1']], false);
        $iade = $this->alisIadeKalemiOlustur($kaynak, $kaynakKalem, $cari, $depo, $stok);
        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
    }

    public function test_olculu_alis_iadesi_ikinci_tam_iadeyi_reddeder(): void
    {
        [$kaynak, $iade] = $this->tamAlisIadeKur();
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        [, $ikinci] = $this->tamAlisIadeKur($kaynak);
        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaIslemServisi::class)->faturayiOnayla($ikinci->fresh());
    }

    public function test_olculu_alis_iadesi_cift_onayda_bakiyeyi_tek_keze_azaltir(): void
    {
        [$kaynak, $iade, $bakiye] = $this->tamAlisIadeKur();
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        $this->assertSame('4.00000000', $bakiye->refresh()->ana_miktar);
        $this->assertSame(1, StokHareketi::query()->where('belge_id', $iade->id)->count());
    }

    public function test_olculu_alis_iadesi_iptalinde_bakiye_geri_doner(): void
    {
        [$kaynak, $iade, $bakiye] = $this->tamAlisIadeKur();
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        app(FaturaIslemServisi::class)->faturayiIptalEt($iade->fresh());
        $this->assertSame('5.00000000', $bakiye->refresh()->ana_miktar);
        $this->assertSame(FaturaDurumu::Iptal, $iade->fresh()->durum);
    }

    public function test_olculu_alis_iadesi_iptal_sonrasi_yeniden_iade_edilebilir(): void
    {
        [$kaynak, $iade, $bakiye] = $this->tamAlisIadeKur();
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        app(FaturaIslemServisi::class)->faturayiIptalEt($iade->fresh());
        [, $yeniden] = $this->tamAlisIadeKur($kaynak);
        app(FaturaIslemServisi::class)->faturayiOnayla($yeniden->fresh());
        $this->assertSame('4.00000000', $bakiye->refresh()->ana_miktar);
        $this->assertSame(FaturaDurumu::Onayli, $yeniden->fresh()->durum);
    }





    public function test_coklu_olculu_alis_iadesi_her_olcuden_cikar(): void
    {
        [$firma, $cari, $depo, $stok, $olcu, $bakiye, $kaynak, $kaynakKalem] = $this->kurulum();
        $kaynak->update(['tur' => FaturaTuru::Gelen->value]);
        $olcu2 = app(StokOlcuBakiyeServisi::class)->olcuOlustur($firma->id, $stok, ['kod' => '100X100', 'ad' => '100x100', 'olcu_birimi' => 'cm', 'en' => '100', 'boy' => '100']);
        $bakiye2 = app(StokOlcuBakiyeServisi::class)->bakiyeBulVeyaOlustur($firma->id, $stok, $olcu2, $depo);
        $birim = Birim::withoutGlobalScopes()->where('kod', 'MTK')->value('id');
        app(FaturaOlcuKalemiServisi::class)->dagilimlariSakla($kaynakKalem, [
            ['stok_olcusu_id' => $olcu->id, 'stok_olcu_bakiyesi_id' => $bakiye->id, 'depo_id' => $depo->id, 'islem_birimi_id' => $birim, 'girilen_miktar' => '2'],
            ['stok_olcusu_id' => $olcu2->id, 'stok_olcu_bakiyesi_id' => $bakiye2->id, 'depo_id' => $depo->id, 'islem_birimi_id' => $birim, 'girilen_miktar' => '2'],
        ], false);
        app(FaturaIslemServisi::class)->faturayiOnayla($kaynak->fresh());
        $iade = $this->alisIadeKalemiOlustur($kaynak, $kaynakKalem, $cari, $depo, $stok, true);
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        $this->assertSame('4.00000000', $bakiye->refresh()->ana_miktar);
        $this->assertSame('0.00000000', $bakiye2->refresh()->ana_miktar);
    }

    public function test_negatif_stok_izinli_olsa_da_yetersiz_alis_olcu_bakiyesi_reddedilir(): void
    {
        [$kaynak, $iade, $bakiye] = $this->tamAlisIadeKur();
        app(FirmaAyarDeposu::class)->yaz((int) $kaynak->firma_id, 'negatif_stok_izinli', true);
        $bakiye->update(['ana_miktar' => '0', 'adet_esdegeri' => '0']);
        $this->expectException(\InvalidArgumentException::class);
        try {
            app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        } finally {
            $this->assertSame(FaturaDurumu::Taslak, $iade->fresh()->durum);
            $this->assertSame(0, StokHareketi::query()->where('belge_id', $iade->id)->count());
            $this->assertSame('0.00000000', $bakiye->refresh()->ana_miktar);
        }
    }



    public function test_iptal_edilmis_alis_kaynagi_olculu_iadeyi_reddeder(): void
    {
        [$kaynak] = $this->tamAlisIadeKur();
        app(FaturaIslemServisi::class)->faturayiIptalEt($kaynak->fresh());
        $iade = $this->tamAlisIadeKur($kaynak)[1];
        $this->expectException(IsKuraliIstisnasi::class);
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
    }

    public function test_olculu_alis_iadesi_ikinci_iptalda_bakiyeleri_degistirmez(): void
    {
        [$kaynak, $iade, $bakiye] = $this->tamAlisIadeKur();
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        app(FaturaIslemServisi::class)->faturayiIptalEt($iade->fresh());
        $ana = $bakiye->refresh()->ana_miktar;
        $this->expectException(IsKuraliIstisnasi::class);
        try {
            app(FaturaIslemServisi::class)->faturayiIptalEt($iade->fresh());
        } finally {
            $this->assertSame($ana, $bakiye->refresh()->ana_miktar);
            $this->assertSame(FaturaDurumu::Iptal, $iade->fresh()->durum);
        }
    }

    public function test_coklu_alis_iadesi_ikinci_dagilim_hatasinda_tam_rollback_uygular(): void
    {
        [$firma, $cari, $depo, $stok, $olcu, $bakiye, $kaynak, $kaynakKalem] = $this->kurulum();
        $kaynak->update(['tur' => FaturaTuru::Gelen->value]);
        $olcu2 = app(StokOlcuBakiyeServisi::class)->olcuOlustur($firma->id, $stok, ['kod' => '100X100-RB', 'ad' => '100x100', 'olcu_birimi' => 'cm', 'en' => '100', 'boy' => '100']);
        $bakiye2 = app(StokOlcuBakiyeServisi::class)->bakiyeBulVeyaOlustur($firma->id, $stok, $olcu2, $depo);
        $birim = Birim::withoutGlobalScopes()->where('kod', 'MTK')->value('id');
        app(FaturaOlcuKalemiServisi::class)->dagilimlariSakla($kaynakKalem, [
            ['stok_olcusu_id' => $olcu->id, 'stok_olcu_bakiyesi_id' => $bakiye->id, 'depo_id' => $depo->id, 'islem_birimi_id' => $birim, 'girilen_miktar' => '2'],
            ['stok_olcusu_id' => $olcu2->id, 'stok_olcu_bakiyesi_id' => $bakiye2->id, 'depo_id' => $depo->id, 'islem_birimi_id' => $birim, 'girilen_miktar' => '2'],
        ], false);
        app(FaturaIslemServisi::class)->faturayiOnayla($kaynak->fresh());
        $iade = $this->alisIadeKalemiOlustur($kaynak, $kaynakKalem, $cari, $depo, $stok, true);
        $dagilimlar = $iade->kalemler()->firstOrFail()->olcuDagilimlari()->orderBy('sira')->get();
        $dagilimlar->last()->update(['kaynak_olcu_dagilimi_id' => $dagilimlar->first()->kaynak_olcu_dagilimi_id]);
        $this->expectException(IsKuraliIstisnasi::class);
        try {
            app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        } finally {
            $this->assertSame(FaturaDurumu::Taslak, $iade->fresh()->durum);
            $this->assertSame(0, StokHareketi::query()->where('belge_id', $iade->id)->count());
            $this->assertSame('6.00000000', $bakiye->refresh()->ana_miktar);
            $this->assertSame('2.00000000', $bakiye2->refresh()->ana_miktar);
        }
    }

    public function test_iptal_edilmis_alis_iadesi_kaynak_baglantisini_korur_ve_yeniden_iade_edilir(): void
    {
        [$kaynak, $iade, $bakiye] = $this->tamAlisIadeKur();
        app(FaturaIslemServisi::class)->faturayiOnayla($iade->fresh());
        $kaynakKalemId = (int) $iade->kalemler()->firstOrFail()->kaynak_fatura_kalemi_id;
        $kaynakDagilimId = (int) $iade->kalemler()->firstOrFail()->olcuDagilimlari()->firstOrFail()->kaynak_olcu_dagilimi_id;
        app(FaturaIslemServisi::class)->faturayiIptalEt($iade->fresh());
        $iptalKalem = $iade->fresh()->kalemler()->firstOrFail();
        $this->assertSame($kaynakKalemId, (int) $iptalKalem->kaynak_fatura_kalemi_id);
        $this->assertSame($kaynakDagilimId, (int) $iptalKalem->olcuDagilimlari()->firstOrFail()->kaynak_olcu_dagilimi_id);
        [, $yeniden] = $this->tamAlisIadeKur($kaynak);
        app(FaturaIslemServisi::class)->faturayiOnayla($yeniden->fresh());
        $this->assertSame(FaturaDurumu::Onayli, $yeniden->fresh()->durum);
        $this->assertSame('4.00000000', $bakiye->refresh()->ana_miktar);
    }

    public function test_olculu_kalem_fiyat_birimi_secenekleri_ana_ve_adettir(): void
    {
        [, , , $stok] = $this->kurulum();
        $secenekler = FaturaKaynagi::olculuFiyatBirimiSecenekleri($stok->id);
        $this->assertArrayHasKey((int) $stok->ana_birim_id, $secenekler);
        $this->assertArrayHasKey((int) $stok->ikincil_birim_id, $secenekler);
    }

    public function test_ana_birim_fiyatindan_adet_fiyatina_geciste_toplam_korunur(): void
    {
        [, , , $stok, $olcu] = $this->kurulum();
        $ana = (int) $stok->ana_birim_id;
        $adet = (int) $stok->ikincil_birim_id;
        $ilk = FaturaKaynagi::hesaplaKalemSatiri(['stok_id' => $stok->id, 'fiyat_birimi_id' => $ana, 'birim_fiyat' => '1000', 'olcu_dagilimlari' => [['stok_olcusu_id' => $olcu->id, 'islem_birimi_id' => $ana, 'girilen_miktar' => '4']]]);
        $son = FaturaKaynagi::hesaplaKalemSatiri(array_merge($ilk, ['fiyat_birimi_id' => $adet]), 'fiyat_birimi_id');
        $this->assertSame('4000.00', number_format((float) $son['birim_fiyat'], 2, '.', ''));
        $this->assertSame($ilk['satir_toplami'], $son['satir_toplami']);
    }

    public function test_adet_fiyatindan_ana_birim_fiyatina_geciste_toplam_korunur(): void
    {
        [, , , $stok, $olcu] = $this->kurulum();
        $ana = (int) $stok->ana_birim_id;
        $adet = (int) $stok->ikincil_birim_id;
        $ilk = FaturaKaynagi::hesaplaKalemSatiri(['stok_id' => $stok->id, 'fiyat_birimi_id' => $adet, 'birim_fiyat' => '4000', 'olcu_dagilimlari' => [['stok_olcusu_id' => $olcu->id, 'islem_birimi_id' => $ana, 'girilen_miktar' => '4']]]);
        $son = FaturaKaynagi::hesaplaKalemSatiri(array_merge($ilk, ['fiyat_birimi_id' => $ana]), 'fiyat_birimi_id');
        $this->assertSame('1000.00', number_format((float) $son['birim_fiyat'], 2, '.', ''));
        $this->assertSame($ilk['satir_toplami'], $son['satir_toplami']);
    }

    public function test_islem_birimi_degisince_ana_ve_adet_miktari_guncellenir(): void
    {
        [, , , $stok, $olcu] = $this->kurulum();
        $sonuc = FaturaKaynagi::hesaplaKalemSatiri(['stok_id' => $stok->id, 'fiyat_birimi_id' => $stok->ana_birim_id, 'birim_fiyat' => '1000', 'olcu_dagilimlari' => [['stok_olcusu_id' => $olcu->id, 'islem_birimi_id' => $stok->ikincil_birim_id, 'girilen_miktar' => '1']]]);
        $this->assertSame('4.00000000', $sonuc['ana_miktar']);
        $this->assertSame('1.00000000', $sonuc['adet_esdegeri']);
    }

    public function test_ayni_katsayili_coklu_olcude_otomatik_adet_fiyati_kabul_edilir(): void
    {
        [, , , $stok, $olcu] = $this->kurulum();
        $ikinci = app(StokOlcuBakiyeServisi::class)->olcuOlustur($stok->firma_id, $stok, ['kod' => '200X200B', 'ad' => '200x200 B', 'olcu_birimi' => 'cm', 'en' => '200', 'boy' => '200']);
        $sonuc = FaturaKaynagi::hesaplaKalemSatiri(['stok_id' => $stok->id, 'fiyat_birimi_id' => $stok->ikincil_birim_id, 'birim_fiyat' => '4000', 'olcu_dagilimlari' => [['stok_olcusu_id' => $olcu->id, 'islem_birimi_id' => $stok->ana_birim_id, 'girilen_miktar' => '4'], ['stok_olcusu_id' => $ikinci->id, 'islem_birimi_id' => $stok->ana_birim_id, 'girilen_miktar' => '4']]]);
        $this->assertSame('4000.00000000', $sonuc['birim_fiyat']);
        $this->assertSame('2.00000000', $sonuc['fiyat_miktari']);
    }

    public function test_farkli_katsayili_coklu_olcude_otomatik_adet_fiyati_reddedilir(): void
    {
        [, , , $stok, $olcu] = $this->kurulum();
        $ikinci = app(StokOlcuBakiyeServisi::class)->olcuOlustur($stok->firma_id, $stok, ['kod' => '100X100', 'ad' => '100x100', 'olcu_birimi' => 'cm', 'en' => '100', 'boy' => '100']);
        $this->expectException(ValidationException::class);
        FaturaKaynagi::hesaplaKalemSatiri(['stok_id' => $stok->id, 'fiyat_birimi_id' => $stok->ikincil_birim_id, 'birim_fiyat' => '4000', 'olcu_dagilimlari' => [['stok_olcusu_id' => $olcu->id, 'islem_birimi_id' => $stok->ana_birim_id, 'girilen_miktar' => '4'], ['stok_olcusu_id' => $ikinci->id, 'islem_birimi_id' => $stok->ana_birim_id, 'girilen_miktar' => '1']]]);
    }

    public function test_farkli_katsayili_payload_onay_katmaninda_reddedilir(): void
    {
        [, , , $stok, $olcu, $bakiye, $fatura, $kalem] = $this->kurulum();
        app(FaturaOlcuKalemiServisi::class)->dagilimlariSakla($kalem, [['stok_olcusu_id' => $olcu->id, 'stok_olcu_bakiyesi_id' => $bakiye->id, 'depo_id' => $stok->depo_id, 'islem_birimi_id' => $stok->ana_birim_id, 'girilen_miktar' => '4']], true);
        $kalem->update(['fiyat_birimi_id' => $stok->ikincil_birim_id, 'fiyat_miktari' => '1', 'olcu_donusum_snapshot' => json_encode(['fiyat_miktari' => '1', 'birim_fiyat' => '4000'])]);
        $kalem->update(['fiyat_miktari' => '999']);
        $this->expectException(\Throwable::class);
        app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());
    }

    public function test_dogrudan_ortak_adet_fiyati_farkli_katsayida_kabul_edilir(): void
    {
        [, , , $stok, $olcu] = $this->kurulum();
        $ikinci = app(StokOlcuBakiyeServisi::class)->olcuOlustur($stok->firma_id, $stok, ['kod' => '100X100C', 'ad' => '100x100 C', 'olcu_birimi' => 'cm', 'en' => '100', 'boy' => '100']);
        $sonuc = FaturaKaynagi::hesaplaKalemSatiri(['stok_id' => $stok->id, 'fiyat_birimi_id' => $stok->ikincil_birim_id, 'dogrudan_ortak_adet_fiyati' => true, 'birim_fiyat' => '2500', 'olcu_dagilimlari' => [['stok_olcusu_id' => $olcu->id, 'islem_birimi_id' => $stok->ana_birim_id, 'girilen_miktar' => '4'], ['stok_olcusu_id' => $ikinci->id, 'islem_birimi_id' => $stok->ana_birim_id, 'girilen_miktar' => '1']]]);
        $this->assertSame('2500.00000000', $sonuc['birim_fiyat']);
        $this->assertStringContainsString('dogrudan_ortak_adet_fiyati', $sonuc['olcu_donusum_snapshot']);
    }

    public function test_stok_degisince_fiyat_olcu_statei_temizlenebilir(): void
    {
        [, , , $stok] = $this->kurulum();
        $standart = StokKarti::create(['firma_id' => $stok->firma_id, 'kod' => 'STD-'.uniqid(), 'ad' => 'Standart', 'tur' => StokKartiTuru::TicariMal->value, 'durum' => HesapDurumu::Aktif->value, 'stok_takip' => true, 'stok_miktari' => '1', 'birim' => 'AD', 'para_birimi' => 'TRY']);
        $this->assertSame([], FaturaKaynagi::olculuFiyatBirimiSecenekleri($standart->id));
    }

    public function test_depo_uyumsuz_olcu_bakiyesi_sunucuda_reddedilir(): void
    {
        [$firma, , $depo, $stok, $olcu, $bakiye, , $kalem] = $this->kurulum();
        $diger = Depo::create(['firma_id' => $firma->id, 'kod' => 'D2', 'ad' => 'Depo 2', 'aktif_mi' => true]);
        $this->expectException(\Throwable::class);
        app(FaturaOlcuKalemiServisi::class)->dagilimlariSakla($kalem, [['stok_olcusu_id' => $olcu->id, 'stok_olcu_bakiyesi_id' => $bakiye->id, 'depo_id' => $diger->id, 'islem_birimi_id' => $stok->ana_birim_id, 'girilen_miktar' => '1']], true);
    }

    public function test_onayda_fiyat_snapshoti_korunur_ve_yeniden_dogrulanir(): void
    {
        [, , $depo, $stok, $olcu, $bakiye, $fatura, $kalem] = $this->kurulum();
        app(FaturaOlcuKalemiServisi::class)->dagilimlariSakla($kalem, [['stok_olcusu_id' => $olcu->id, 'stok_olcu_bakiyesi_id' => $bakiye->id, 'depo_id' => $depo->id, 'islem_birimi_id' => $stok->ana_birim_id, 'girilen_miktar' => '4']], true);
        $hesap = FaturaKaynagi::hesaplaKalemSatiri(['stok_id' => $stok->id, 'fiyat_birimi_id' => $stok->ana_birim_id, 'birim_fiyat' => '1000', 'olcu_dagilimlari' => [['stok_olcusu_id' => $olcu->id, 'islem_birimi_id' => $stok->ana_birim_id, 'girilen_miktar' => '4']]]);
        $kalem->update(['fiyat_birimi_id' => $hesap['fiyat_birimi_id'], 'fiyat_miktari' => $hesap['fiyat_miktari'], 'olcu_donusum_snapshot' => $hesap['olcu_donusum_snapshot'], 'birim_fiyat' => $hesap['birim_fiyat']]);
        app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());
        $this->assertNotNull($kalem->refresh()->olcu_donusum_snapshot);
    }

    public function test_manipule_edilmis_toplam_yeniden_hesaplanir(): void
    {
        [, , , $stok, $olcu] = $this->kurulum();
        $sonuc = FaturaKaynagi::hesaplaKalemSatiri(['stok_id' => $stok->id, 'miktar' => '999', 'birim_fiyat' => '1000', 'toplam' => '1', 'olcu_dagilimlari' => [['stok_olcusu_id' => $olcu->id, 'islem_birimi_id' => $stok->ana_birim_id, 'girilen_miktar' => '4']]]);
        $this->assertNotSame('1', (string) $sonuc['toplam']);
    }

    public function test_standart_kalem_fiyat_akisi_degisimez(): void
    {
        [, , , $stok] = $this->kurulum();
        $standart = StokKarti::create(['firma_id' => $stok->firma_id, 'kod' => 'STD-'.uniqid(), 'ad' => 'Standart', 'tur' => StokKartiTuru::TicariMal->value, 'durum' => HesapDurumu::Aktif->value, 'stok_takip' => true, 'stok_miktari' => '1', 'birim' => 'AD', 'para_birimi' => 'TRY']);
        $sonuc = FaturaKaynagi::hesaplaKalemSatiri(['stok_id' => $standart->id, 'miktar' => '2', 'birim_fiyat' => '15', 'kdv_orani' => '0']);
        $this->assertSame(30.0, $sonuc['satir_toplami']);
    }

    public function test_sifir_olcu_fiyati_formda_reddedilir(): void
    {
        [, , , $stok, $olcu] = $this->kurulum();
        $this->expectException(ValidationException::class);
        FaturaKaynagi::hesaplaKalemSatiri(['stok_id' => $stok->id, 'fiyat_birimi_id' => $stok->ana_birim_id, 'birim_fiyat' => '0', 'olcu_dagilimlari' => [['stok_olcusu_id' => $olcu->id, 'islem_birimi_id' => $stok->ana_birim_id, 'girilen_miktar' => '4']]]);
    }

    public function test_fiyat_snapshot_hatasi_stok_hareketini_rollback_eder(): void
    {
        [, , $depo, $stok, $olcu, $bakiye, $fatura, $kalem] = $this->kurulum();
        app(FaturaOlcuKalemiServisi::class)->dagilimlariSakla($kalem, [['stok_olcusu_id' => $olcu->id, 'stok_olcu_bakiyesi_id' => $bakiye->id, 'depo_id' => $depo->id, 'islem_birimi_id' => $stok->ana_birim_id, 'girilen_miktar' => '4']], true);
        $kalem->update(['fiyat_birimi_id' => $stok->ana_birim_id, 'fiyat_miktari' => '999', 'olcu_donusum_snapshot' => json_encode(['fiyat_miktari' => '999', 'birim_fiyat' => '1000'])]);
        $this->expectException(\Throwable::class);
        try {
            app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());
        } finally {
            $this->assertSame('4.00000000', $bakiye->refresh()->ana_miktar);
            $this->assertSame(0, StokHareketi::query()->where('belge_id', $fatura->id)->count());
        }
    }

    public function test_olculu_stok_secildiginde_depo_form_alani_gorunur(): void
    {
        [$firma, , , $stok] = $this->kurulum();

        $this->assertTrue(FaturaKaynagi::depoAlanGosterilmeli($firma->id, $stok->id));
    }

    public function test_olculu_stok_ve_depo_secildiginde_olcu_dagilimi_form_alani_gorunur(): void
    {
        [, , $depo, $stok] = $this->kurulum();

        $this->assertGreaterThan(0, $depo->id);
        $this->assertTrue(FaturaKaynagi::olcuDagilimiAlanGosterilmeli($stok->id));
    }

    public function test_coklu_olculu_stokta_tum_aktif_olcu_secenekleri_gelir(): void
    {
        [, , , $stok, $olcu] = $this->kurulum();
        $ikinci = app(StokOlcuBakiyeServisi::class)->olcuOlustur($stok->firma_id, $stok, [
            'kod' => '200X200-B', 'ad' => '200x200 B', 'olcu_birimi' => 'cm', 'en' => '200', 'boy' => '200',
        ]);

        $olcuIdleri = StokOlcusu::withoutGlobalScopes()->where('stok_id', $stok->id)->where('aktif_mi', true)->pluck('id');
        $this->assertContains($olcu->id, $olcuIdleri->all());
        $this->assertContains($ikinci->id, $olcuIdleri->all());
        $this->assertCount(2, $olcuIdleri);
    }

    public function test_standart_stokta_olcu_depo_ve_fiyat_birimi_form_alanlari_gizlidir(): void
    {
        [$firma, , , $olculu] = $this->kurulum();
        $standart = StokKarti::create([
            'firma_id' => $firma->id, 'kod' => 'STD-FORM-'.uniqid(), 'ad' => 'Standart form',
            'tur' => StokKartiTuru::TicariMal->value, 'durum' => HesapDurumu::Aktif->value,
            'stok_takip' => true, 'stok_miktari' => '1', 'birim' => 'AD', 'para_birimi' => 'TRY',
        ]);

        $this->assertFalse(FaturaKaynagi::depoAlanGosterilmeli($firma->id, $standart->id));
        $this->assertFalse(FaturaKaynagi::olcuDagilimiAlanGosterilmeli($standart->id));
        $this->assertSame([], FaturaKaynagi::olculuFiyatBirimiSecenekleri($standart->id));
        $this->assertTrue(FaturaKaynagi::olcuDagilimiAlanGosterilmeli($olculu->id));
    }

    public function test_stok_degisince_eski_olcu_dagilimi_yeni_stokta_gecersizdir(): void
    {
        [, , , $stok, $olcu] = $this->kurulum();
        $standart = StokKarti::create([
            'firma_id' => $stok->firma_id, 'kod' => 'STD-STATE-'.uniqid(), 'ad' => 'State standart',
            'tur' => StokKartiTuru::TicariMal->value, 'durum' => HesapDurumu::Aktif->value,
            'stok_takip' => true, 'stok_miktari' => '1', 'birim' => 'AD', 'para_birimi' => 'TRY',
        ]);

        $this->assertTrue(FaturaKaynagi::olcuDagilimiAlanGosterilmeli($stok->id));
        $this->assertFalse(FaturaKaynagi::olcuDagilimiAlanGosterilmeli($standart->id));
        $this->assertSame([], FaturaKaynagi::olculuFiyatBirimiSecenekleri($standart->id));
        $this->assertNotNull($olcu->id);
    }

    public function test_depo_degisince_eski_olcu_bakiyesi_yeni_depo_seceneklerinde_yoktur(): void
    {
        [$firma, , $depo, $stok] = $this->kurulum();
        $diger = Depo::create(['firma_id' => $firma->id, 'kod' => 'D2-'.uniqid(), 'ad' => 'Diğer depo', 'aktif_mi' => true]);

        $this->assertArrayHasKey($depo->id, FaturaKaynagi::depoSecenekleriForForm($firma->id, $stok->id));
        $this->assertArrayNotHasKey($diger->id, FaturaKaynagi::depoSecenekleriForForm($firma->id, $stok->id));
    }

    public function test_olculu_fiyat_biriminde_gercek_adet_ve_ana_birim_idleri_kullanilir(): void
    {
        [, , , $stok] = $this->kurulum();
        $secenekler = FaturaKaynagi::olculuFiyatBirimiSecenekleri($stok->id);

        $this->assertArrayHasKey((int) $stok->ana_birim_id, $secenekler);
        $this->assertArrayHasKey((int) $stok->ikincil_birim_id, $secenekler);
        $this->assertSame([], array_filter(array_keys($secenekler), static fn ($id): bool => (string) $id === 'AD'));
    }

    public function test_olculu_islem_birimi_secenekleri_gercek_birim_idleri_kullanir(): void
    {
        [, , , $stok] = $this->kurulum();
        $secenekler = FaturaKaynagi::olculuIslemBirimiSecenekleri($stok->id);

        $this->assertArrayHasKey((int) $stok->ana_birim_id, $secenekler);
        $this->assertArrayHasKey((int) $stok->ikincil_birim_id, $secenekler);
        $this->assertSame([], array_filter(array_keys($secenekler), static fn ($id): bool => is_string($id) && ! ctype_digit($id)));
    }

    public function test_veritabaninda_olmayan_ad_fiyat_birimi_payloadu_kabul_edilmez(): void
    {
        [, , , $stok, $olcu] = $this->kurulum();
        $this->expectException(ValidationException::class);

        FaturaKaynagi::hesaplaKalemSatiri([
            'stok_id' => $stok->id, 'fiyat_birimi_id' => 'AD', 'birim_fiyat' => '1000',
            'olcu_dagilimlari' => [['stok_olcusu_id' => $olcu->id, 'islem_birimi_id' => $stok->ana_birim_id, 'girilen_miktar' => '4']],
        ]);
    }

    public function test_onayli_faturada_olcu_fiyat_alanlari_sunucu_tarafinda_korunur(): void
    {
        [, , $depo, $stok, $olcu, $bakiye, $fatura, $kalem] = $this->kurulum();
        app(FaturaOlcuKalemiServisi::class)->dagilimlariSakla($kalem, [[
            'stok_olcusu_id' => $olcu->id, 'stok_olcu_bakiyesi_id' => $bakiye->id, 'depo_id' => $depo->id,
            'islem_birimi_id' => $stok->ana_birim_id, 'girilen_miktar' => '4',
        ]], true);
        $hesap = FaturaKaynagi::hesaplaKalemSatiri([
            'stok_id' => $stok->id, 'fiyat_birimi_id' => $stok->ana_birim_id, 'birim_fiyat' => '1000',
            'olcu_dagilimlari' => [['stok_olcusu_id' => $olcu->id, 'islem_birimi_id' => $stok->ana_birim_id, 'girilen_miktar' => '4']],
        ]);
        $kalem->update(['fiyat_birimi_id' => $hesap['fiyat_birimi_id'], 'fiyat_miktari' => $hesap['fiyat_miktari'], 'olcu_donusum_snapshot' => $hesap['olcu_donusum_snapshot'], 'birim_fiyat' => $hesap['birim_fiyat']]);
        app(FaturaIslemServisi::class)->faturayiOnayla($fatura->fresh());

        $this->assertSame(FaturaDurumu::Onayli, $fatura->fresh()->durum);
        $this->assertNotNull($kalem->refresh()->olcu_donusum_snapshot);
    }

    public function test_stok_arama_tam_kodda_tenant_stogunu_doner(): void
    {
        [$firma, , , $stok] = $this->kurulum();
        $diger = StokKarti::create([
            'firma_id' => $firma->id,
            'kod' => 'DIGER-'.uniqid(),
            'ad' => 'Başka stok',
            'tur' => StokKartiTuru::TicariMal->value,
            'durum' => HesapDurumu::Aktif->value,
            'stok_takip' => true,
            'stok_miktari' => '1',
            'birim' => 'AD',
            'para_birimi' => 'TRY',
        ]);

        $sonuclar = FaturaKaynagi::stokAdAramaSonuclari($stok->kod, $firma->id);

        $this->assertSame([(string) $stok->id => $stok->kod.' - '.$stok->ad], $sonuclar);
        $this->assertArrayNotHasKey((string) $diger->id, $sonuclar);
    }

    /** @return array{0:Fatura,1:Fatura,2:StokOlcuBakiyesi} */
    private function tamAlisIadeKur(?Fatura $kaynak = null): array
    {
        if ($kaynak === null) {
            [, $cari, $depo, $stok, $olcu, $bakiye, $kaynak, $kaynakKalem] = $this->kurulum();
            $kaynak->update(['tur' => FaturaTuru::Gelen->value]);
            app(FaturaOlcuKalemiServisi::class)->dagilimlariSakla($kaynakKalem, [['stok_olcusu_id' => $olcu->id, 'stok_olcu_bakiyesi_id' => $bakiye->id, 'depo_id' => $depo->id, 'islem_birimi_id' => Birim::withoutGlobalScopes()->where('kod', 'MTK')->value('id'), 'girilen_miktar' => '1']], false);
            app(FaturaIslemServisi::class)->faturayiOnayla($kaynak->fresh());
        } else {
            $cari = $kaynak->cari;
            $depo = Depo::where('firma_id', $kaynak->firma_id)->firstOrFail();
            $stok = $kaynak->kalemler()->firstOrFail()->stokKarti;
            $olcu = $stok->olculer()->firstOrFail();
            $bakiye = StokOlcuBakiyesi::withoutGlobalScopes()->where('stok_id', $stok->id)->where('stok_olcusu_id', $olcu->id)->firstOrFail();
            $kaynakKalem = $kaynak->kalemler()->firstOrFail();
        }
        $iade = $this->alisIadeKalemiOlustur($kaynak, $kaynakKalem, $cari, $depo, $stok);

        return [$kaynak, $iade, $bakiye];
    }

    private function alisIadeKalemiOlustur(Fatura $kaynak, FaturaKalemi $kaynakKalem, Cari $cari, Depo $depo, StokKarti $stok, bool $coklu = false): Fatura
    {
        $iade = Fatura::create(['firma_id' => $kaynak->firma_id, 'cari_id' => $cari->id, 'bagli_fatura_id' => $kaynak->id, 'tur' => FaturaTuru::AlisIadesi->value, 'durum' => FaturaDurumu::Taslak->value, 'tarih' => now(), 'ara_toplam' => '1000', 'kdv_toplam' => '0', 'genel_toplam' => '1000', 'toplam_indirim' => '0', 'odenecek_tutar' => '1000', 'odendi_tutari' => '0', 'acik_tutar' => '1000', 'para_birimi' => 'TRY', 'doviz_kuru' => '1']);
        $kalem = FaturaKalemi::create(['firma_id' => $kaynak->firma_id, 'fatura_id' => $iade->id, 'kaynak_fatura_kalemi_id' => $kaynakKalem->id, 'satir_no' => 1, 'stok_id' => $stok->id, 'depo_id' => $depo->id, 'miktar' => '1', 'ana_miktar' => '1', 'birim' => 'MTK', 'birim_fiyat' => '1000', 'kdv_orani' => '0', 'net_tutar' => '1000', 'toplam' => '1000', 'satir_toplami' => '1000', 'satir_genel_toplam' => '1000', 'para_birimi' => 'TRY']);
        $sources = $coklu ? $kaynakKalem->olcuDagilimlari()->orderBy('sira')->get() : collect([$kaynakKalem->olcuDagilimlari()->firstOrFail()]);
        app(FaturaOlcuKalemiServisi::class)->dagilimlariSakla($kalem, $sources->map(fn ($source): array => ['stok_olcusu_id' => $source->stok_olcusu_id, 'stok_olcu_bakiyesi_id' => $source->stok_olcu_bakiyesi_id, 'depo_id' => $source->depo_id, 'kaynak_olcu_dagilimi_id' => $source->id, 'islem_birimi_id' => $source->islem_birimi_id, 'girilen_miktar' => (string) $source->girilen_miktar])->all(), true);

        return $iade->fresh();
    }

    /** @return array{0:Fatura,1:Fatura,2:StokOlcuBakiyesi} */
    private function tamIadeKur(?Fatura $kaynak = null): array
    {
        if ($kaynak === null) {
            [, $cari, $depo, $stok, $olcu, $bakiye, $kaynak, $kaynakKalem] = $this->kurulum();
            app(FaturaOlcuKalemiServisi::class)->dagilimlariSakla($kaynakKalem, [['stok_olcusu_id' => $olcu->id, 'stok_olcu_bakiyesi_id' => $bakiye->id, 'depo_id' => $depo->id, 'islem_birimi_id' => Birim::withoutGlobalScopes()->where('kod', 'MTK')->value('id'), 'girilen_miktar' => '1']], true);
            app(FaturaIslemServisi::class)->faturayiOnayla($kaynak->fresh());
        } else {
            $cari = $kaynak->cari;
            $depo = Depo::where('firma_id', $kaynak->firma_id)->firstOrFail();
            $stok = $kaynak->kalemler()->firstOrFail()->stokKarti;
            $olcu = $stok->olculer()->firstOrFail();
            $bakiye = StokOlcuBakiyesi::withoutGlobalScopes()->where('stok_id', $stok->id)->where('stok_olcusu_id', $olcu->id)->firstOrFail();
            $kaynakKalem = $kaynak->kalemler()->firstOrFail();
        }
        $iade = $this->iadeKalemiOlustur($kaynak, $kaynakKalem, $cari, $depo, $stok, $olcu, $bakiye);

        return [$kaynak, $iade, $bakiye];
    }

    private function iadeKalemiOlustur(Fatura $kaynak, FaturaKalemi $kaynakKalem, Cari $cari, Depo $depo, StokKarti $stok, StokOlcusu $olcu, StokOlcuBakiyesi $bakiye): Fatura
    {
        $iade = Fatura::create(['firma_id' => $kaynak->firma_id, 'cari_id' => $cari->id, 'bagli_fatura_id' => $kaynak->id, 'tur' => FaturaTuru::SatisIadesi->value, 'durum' => FaturaDurumu::Taslak->value, 'tarih' => now(), 'ara_toplam' => '1000', 'kdv_toplam' => '0', 'genel_toplam' => '1000', 'toplam_indirim' => '0', 'odenecek_tutar' => '1000', 'odendi_tutari' => '0', 'acik_tutar' => '1000', 'para_birimi' => 'TRY', 'doviz_kuru' => '1']);
        $kalem = FaturaKalemi::create(['firma_id' => $kaynak->firma_id, 'fatura_id' => $iade->id, 'kaynak_fatura_kalemi_id' => $kaynakKalem->id, 'satir_no' => 1, 'stok_id' => $stok->id, 'depo_id' => $depo->id, 'miktar' => '1', 'ana_miktar' => '1', 'birim' => 'MTK', 'birim_fiyat' => '1000', 'kdv_orani' => '0', 'net_tutar' => '1000', 'toplam' => '1000', 'satir_toplami' => '1000', 'satir_genel_toplam' => '1000', 'para_birimi' => 'TRY']);
        $source = $kaynakKalem->olcuDagilimlari()->firstOrFail();
        app(FaturaOlcuKalemiServisi::class)->dagilimlariSakla($kalem, [['stok_olcusu_id' => $source->stok_olcusu_id, 'stok_olcu_bakiyesi_id' => $source->stok_olcu_bakiyesi_id, 'depo_id' => $source->depo_id, 'kaynak_olcu_dagilimi_id' => $source->id, 'islem_birimi_id' => $source->islem_birimi_id, 'girilen_miktar' => (string) $source->girilen_miktar]], false);

        return $iade->fresh();
    }

    private function kurulum(): array
    {
        $firma = Firma::create(['ad' => 'Fatura ölçü', 'kisa_ad' => 'FÖ', 'firma_kodu' => 'FO-'.uniqid(), 'durum' => Firma::DURUM_AKTIF, 'onaylandi_mi' => true]);
        $this->actingAs(User::create(['name' => 'Ölçü SA', 'email' => 'fo-'.uniqid().'@test.local', 'password' => bcrypt('x'), 'super_admin_mi' => true]));
        $cari = Cari::create(['firma_id' => $firma->id, 'kod' => 'C-1', 'ad' => 'Cari', 'tur' => CariTuru::Musteri->value, 'durum' => CariDurumu::Aktif->value]);
        $depo = Depo::create(['firma_id' => $firma->id, 'kod' => 'D1', 'ad' => 'Depo', 'aktif_mi' => true]);
        $this->seed(MuhasebeOlcuBirimleriSeeder::class);
        $m2 = Birim::withoutGlobalScopes()->where('kod', 'MTK')->firstOrFail();
        $ad = Birim::withoutGlobalScopes()->where('kod', 'AD')->firstOrFail();
        $stok = StokKarti::create(['firma_id' => $firma->id, 'kod' => 'M-'.uniqid(), 'ad' => 'Mermer', 'tur' => StokKartiTuru::TicariMal->value, 'durum' => HesapDurumu::Aktif->value, 'stok_takip' => true, 'stok_miktari' => '4', 'depo_id' => $depo->id, 'para_birimi' => 'TRY', 'olculu_takip_turu' => 'alan', 'ana_birim_id' => $m2->id, 'ikincil_birim_id' => $ad->id]);
        $olcu = app(StokOlcuBakiyeServisi::class)->olcuOlustur($firma->id, $stok, ['kod' => '200X200', 'ad' => '200x200', 'olcu_birimi' => 'cm', 'en' => '200', 'boy' => '200']);
        $bakiye = app(StokOlcuBakiyeServisi::class)->bakiyeBulVeyaOlustur($firma->id, $stok, $olcu, $depo);
        app(StokOlcuBakiyeServisi::class)->giris($bakiye, anaMiktar: '4');
        $fatura = Fatura::create(['firma_id' => $firma->id, 'cari_id' => $cari->id, 'tur' => FaturaTuru::Giden->value, 'durum' => FaturaDurumu::Taslak->value, 'tarih' => now(), 'ara_toplam' => '1000', 'kdv_toplam' => '0', 'genel_toplam' => '1000', 'toplam_indirim' => '0', 'odenecek_tutar' => '1000', 'odendi_tutari' => '0', 'acik_tutar' => '1000', 'para_birimi' => 'TRY', 'doviz_kuru' => '1']);
        $kalem = FaturaKalemi::create(['firma_id' => $firma->id, 'fatura_id' => $fatura->id, 'satir_no' => 1, 'stok_id' => $stok->id, 'depo_id' => $depo->id, 'miktar' => '1', 'birim' => 'MTK', 'birim_fiyat' => '1000', 'kdv_orani' => '0', 'net_tutar' => '1000', 'toplam' => '1000', 'satir_toplami' => '1000', 'satir_genel_toplam' => '1000', 'para_birimi' => 'TRY']);

        return [$firma, $cari, $depo, $stok, $olcu, $bakiye, $fatura, $kalem];
    }
}
