<?php

namespace App\Services;

use App\Models\Ecommerce\Siparis;
use App\Models\Ecommerce\SiparisKalemi;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\FinansHareketi;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Servisler\FinansHareketServisi;
use App\Support\DenetimYardimcisi;
use Illuminate\Support\Facades\DB;

class EcommerceMuhasebeEntegrasyonServisi
{
    private const PARA_BASAMAK = 8;
    private const MIN_TUTAR_FARKI = 0.00000001;

    public function __construct(
        private readonly EcommerceCariServisi $ecommerceCariServisi,
        private readonly EcommerceMuhasebeOdemeHedefServisi $odemeHedefServisi,
        private readonly FinansHareketServisi $finansHareketServisi,
    ) {}

    public function siparisiMuhasebeyeEntegreEt(Siparis $siparis): Siparis
    {
        return DB::transaction(function () use ($siparis): Siparis {
            /** @var Siparis $kilitli */
            $kilitli = Siparis::query()
                ->with(['kalemler', 'kullanici', 'odemeYontemi'])
                ->whereKey($siparis->id)
                ->lockForUpdate()
                ->firstOrFail();

            $cari = $this->ecommerceCariServisi->siparisIcinCariOlusturVeyaGuncelle($kilitli);
            $fatura = $this->proformaFaturaOlusturVeyaGetir($kilitli, (int) $cari->id);
            $finans = $this->tahsilatKaydiOlusturVeyaGetir($kilitli, (int) $cari->id);

            $kilitli->update([
                'muhasebe_cari_id' => (int) $cari->id,
                'proforma_fatura_id' => (int) $fatura->id,
                'tahsilat_finans_hareketi_id' => $finans ? (int) $finans->id : null,
                'muhasebe_entegrasyon_durumu' => 'tamamlandi',
                'muhasebe_entegrasyon_notu' => 'Sipariş muhasebeye aktarıldı.',
                'muhasebe_entegrasyon_at' => now(),
            ]);

            DenetimYardimcisi::kaydet(
                olay: 'ecommerce.muhasebe_entegrasyonu.tamamlandi',
                konuTipi: Siparis::class,
                konuId: (int) $kilitli->id,
                firmaId: (int) $kilitli->firma_id,
                eskiVeri: null,
                yeniVeri: [
                    'muhasebe_cari_id' => (int) $cari->id,
                    'proforma_fatura_id' => (int) $fatura->id,
                    'tahsilat_finans_hareketi_id' => $finans ? (int) $finans->id : null,
                ],
            );

            return $kilitli->fresh(['muhasebeCari', 'proformaFatura', 'tahsilatFinansHareketi']) ?? $kilitli;
        });
    }

    private function proformaFaturaOlusturVeyaGetir(Siparis $siparis, int $cariId): Fatura
    {
        $mevcutId = (int) ($siparis->proforma_fatura_id ?? 0);
        if ($mevcutId > 0) {
            $mevcut = Fatura::query()->withoutGlobalScopes()->whereKey($mevcutId)->first();
            if ($mevcut) {
                return $mevcut;
            }
        }

        $ozet = $this->faturaKalemleriniVeToplamlariniHazirla($siparis);
        $kalemler = $ozet['kalemler'];
        $toplamlar = $ozet['toplamlar'];
        $belgeNo = $this->proformaBelgeNoUret((int) $siparis->firma_id);

        $fatura = Fatura::query()->create([
            'firma_id' => (int) $siparis->firma_id,
            'cari_id' => $cariId,
            'belge_no' => $belgeNo,
            'fatura_no' => $belgeNo,
            'tur' => FaturaTuru::Proforma,
            'durum' => FaturaDurumu::Onayli,
            'tarih' => now(),
            'vade_tarihi' => now()->toDateString(),
            'doviz_kuru' => 1,
            'ara_toplam' => $toplamlar['ara_toplam'],
            'baz_ara_toplam' => $toplamlar['ara_toplam'],
            'toplam_indirim' => $toplamlar['toplam_indirim'],
            'baz_toplam_indirim' => $toplamlar['toplam_indirim'],
            'kdv_toplam' => $toplamlar['kdv_toplam'],
            'baz_kdv_toplam' => $toplamlar['kdv_toplam'],
            'tevkifat_orani' => 0,
            'genel_toplam' => $toplamlar['genel_toplam'],
            'baz_genel_toplam' => $toplamlar['genel_toplam'],
            'odenecek_tutar' => $toplamlar['genel_toplam'],
            'baz_odenecek_tutar' => $toplamlar['genel_toplam'],
            'odendi_tutari' => 0,
            'baz_odendi_tutari' => 0,
            'acik_tutar' => $toplamlar['genel_toplam'],
            'baz_acik_tutar' => $toplamlar['genel_toplam'],
            'genel_indirim_tutari' => $toplamlar['toplam_indirim'],
            'kdv_dahil_fiyatlandirma_mi' => false,
            'para_birimi' => strtoupper((string) ($siparis->para_birimi ?? 'TRY')),
            'baz_para_birimi' => strtoupper((string) ($siparis->para_birimi ?? 'TRY')),
            'aciklama' => 'E-ticaret siparişi proforma faturası',
            'notlar' => 'Sipariş No: '.$siparis->siparis_no,
            'kaynak_tipi' => 'ecommerce_siparis',
        ]);

        foreach ($kalemler as $index => $kalem) {
            FaturaKalemi::query()->create(array_merge($kalem, [
                'firma_id' => (int) $siparis->firma_id,
                'fatura_id' => (int) $fatura->id,
                'satir_no' => $index + 1,
            ]));
        }

        return $fatura;
    }

    private function tahsilatKaydiOlusturVeyaGetir(Siparis $siparis, int $cariId): ?FinansHareketi
    {
        $mevcutId = (int) ($siparis->tahsilat_finans_hareketi_id ?? 0);
        if ($mevcutId > 0) {
            $mevcut = FinansHareketi::query()->withoutGlobalScopes()->whereKey($mevcutId)->first();
            if ($mevcut) {
                return $mevcut;
            }
        }

        $onceki = FinansHareketi::query()
            ->withoutGlobalScopes()
            ->where('firma_id', (int) $siparis->firma_id)
            ->where('referans_turu', Siparis::REFERANS_TURU_FINANS)
            ->where('referans_id', (int) $siparis->id)
            ->orderByDesc('id')
            ->first();

        if ($onceki) {
            return $onceki;
        }

        $hedef = $this->odemeHedefServisi->siparisIcinTahsilatHedefi($siparis);
        $aciklama = 'E-ticaret sipariş tahsilatı '.$siparis->siparis_no;

        $sonuc = match ($hedef['kanal']) {
            'kasa' => $this->finansHareketServisi->tahsilatKasadanEcommerceKaydet(
                (int) $siparis->firma_id,
                $cariId,
                (int) $hedef['hesap_id'],
                (string) $siparis->genel_toplam,
                (string) ($siparis->para_birimi ?? 'TRY'),
                now(),
                $aciklama,
                Siparis::REFERANS_TURU_FINANS,
                (int) $siparis->id,
            ),
            'banka' => $this->finansHareketServisi->tahsilatBankadanEcommerceKaydet(
                (int) $siparis->firma_id,
                $cariId,
                (int) $hedef['hesap_id'],
                (string) $siparis->genel_toplam,
                (string) ($siparis->para_birimi ?? 'TRY'),
                now(),
                $aciklama,
                Siparis::REFERANS_TURU_FINANS,
                (int) $siparis->id,
            ),
            'pos' => $this->finansHareketServisi->tahsilatPosEcommerceKaydet(
                (int) $siparis->firma_id,
                $cariId,
                (int) $hedef['hesap_id'],
                (string) $siparis->genel_toplam,
                (string) ($siparis->para_birimi ?? 'TRY'),
                now(),
                $aciklama,
                Siparis::REFERANS_TURU_FINANS,
                (int) $siparis->id,
            ),
            default => null,
        };

        return is_array($sonuc) ? ($sonuc['finans'] ?? null) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function faturaKalemPayloadlari(Siparis $siparis): array
    {
        $siparis->loadMissing('kalemler');

        $urunKalemleri = $siparis->kalemler
            ->filter(fn (SiparisKalemi $kalem): bool => (float) $kalem->miktar > 0)
            ->values();

        $araToplam = max(0.0, (float) $siparis->ara_toplam);
        $indirimToplami = max(0.0, min((float) $siparis->indirim_toplami, $araToplam));
        $kalanIndirim = $indirimToplami;
        $payloadlar = [];
        $adet = $urunKalemleri->count();

        foreach ($urunKalemleri as $index => $kalem) {
            $satirToplami = $this->paraYuvarla((float) $kalem->satir_toplami);
            $oran = $araToplam > 0 ? ($satirToplami / $araToplam) : 0;
            $indirimTutari = $index === ($adet - 1)
                ? $this->paraYuvarla($kalanIndirim)
                : $this->paraYuvarla($indirimToplami * $oran);

            $indirimTutari = max(0.0, min($indirimTutari, $satirToplami));
            $kalanIndirim = $this->paraYuvarla($kalanIndirim - $indirimTutari);
            $netTutar = $this->paraYuvarla($satirToplami - $indirimTutari);
            $kdvTutari = $this->paraYuvarla($netTutar * ((float) $kalem->kdv_orani / 100));
            $genelToplam = $this->paraYuvarla($netTutar + $kdvTutari);

            $payloadlar[] = [
                'kalem_tipi' => 'stok_kalemi',
                'stok_id' => (int) $kalem->stok_karti_id,
                'seri_nolari' => array_values(array_filter(array_map('trim', (array) ($kalem->seri_nolari ?? [])))),
                'birim' => 'AD',
                'hizmet_mi' => false,
                'aciklama' => (string) $kalem->urun_adi_snapshot,
                'miktar' => (float) $kalem->miktar,
                'birim_fiyat' => $this->paraYuvarla((float) $kalem->birim_fiyat),
                'baz_birim_fiyat' => $this->paraYuvarla((float) $kalem->birim_fiyat),
                'indirim_orani' => $satirToplami > 0 ? round(($indirimTutari / $satirToplami) * 100, 2) : 0,
                'kdv_orani' => round((float) $kalem->kdv_orani, 2),
                'satir_indirim_tutari' => $indirimTutari,
                'indirim_tutari' => $indirimTutari,
                'baz_indirim_tutari' => $indirimTutari,
                'net_tutar' => $netTutar,
                'baz_net_tutar' => $netTutar,
                'kdv_tutari' => $kdvTutari,
                'baz_kdv_tutari' => $kdvTutari,
                'satir_toplami' => $satirToplami,
                'baz_satir_toplami' => $satirToplami,
                'satir_genel_toplam' => $genelToplam,
                'baz_satir_genel_toplam' => $genelToplam,
                'para_birimi' => strtoupper((string) ($siparis->para_birimi ?? 'TRY')),
                'baz_para_birimi' => strtoupper((string) ($siparis->para_birimi ?? 'TRY')),
                'toplam' => $genelToplam,
            ];
        }

        if ((float) $siparis->kargo_ucreti > 0) {
            $kargoTutari = $this->paraYuvarla((float) $siparis->kargo_ucreti);
            $payloadlar[] = [
                'kalem_tipi' => 'hizmet_kalemi',
                'stok_id' => null,
                'birim' => 'AD',
                'hizmet_mi' => true,
                'aciklama' => trim('Kargo Bedeli '.((string) ($siparis->kargo_firmasi ?? '') !== '' ? '- '.$siparis->kargo_firmasi : '')),
                'miktar' => 1,
                'birim_fiyat' => $kargoTutari,
                'baz_birim_fiyat' => $kargoTutari,
                'indirim_orani' => 0,
                'kdv_orani' => 0,
                'satir_indirim_tutari' => 0,
                'indirim_tutari' => 0,
                'baz_indirim_tutari' => 0,
                'net_tutar' => $kargoTutari,
                'baz_net_tutar' => $kargoTutari,
                'kdv_tutari' => 0,
                'baz_kdv_tutari' => 0,
                'satir_toplami' => $kargoTutari,
                'baz_satir_toplami' => $kargoTutari,
                'satir_genel_toplam' => $kargoTutari,
                'baz_satir_genel_toplam' => $kargoTutari,
                'para_birimi' => strtoupper((string) ($siparis->para_birimi ?? 'TRY')),
                'baz_para_birimi' => strtoupper((string) ($siparis->para_birimi ?? 'TRY')),
                'toplam' => $kargoTutari,
            ];
        }

        return $payloadlar;
    }

    /**
     * @return array{
     *   kalemler: array<int, array<string, mixed>>,
     *   toplamlar: array{ara_toplam:float,toplam_indirim:float,kdv_toplam:float,genel_toplam:float}
     * }
     */
    private function faturaKalemleriniVeToplamlariniHazirla(Siparis $siparis): array
    {
        $kalemler = $this->faturaKalemPayloadlari($siparis);
        $araToplam = $this->paraYuvarla((float) collect($kalemler)->sum('net_tutar'));
        $toplamIndirim = $this->paraYuvarla((float) collect($kalemler)->sum('indirim_tutari'));
        $kdvToplam = $this->paraYuvarla((float) collect($kalemler)->sum('kdv_tutari'));
        $genelToplam = $this->paraYuvarla((float) collect($kalemler)->sum('satir_genel_toplam'));
        $siparisGenelToplam = $this->paraYuvarla((float) ($siparis->genel_toplam ?? 0));

        if (abs($genelToplam - $siparisGenelToplam) >= self::MIN_TUTAR_FARKI && count($kalemler) > 0) {
            $fark = $this->paraYuvarla($siparisGenelToplam - $genelToplam);
            $sonIndex = array_key_last($kalemler);
            if ($sonIndex !== null) {
                $kalemler[$sonIndex]['net_tutar'] = $this->paraYuvarla((float) $kalemler[$sonIndex]['net_tutar'] + $fark);
                $kalemler[$sonIndex]['baz_net_tutar'] = $kalemler[$sonIndex]['net_tutar'];
                $kalemler[$sonIndex]['satir_genel_toplam'] = $this->paraYuvarla((float) $kalemler[$sonIndex]['satir_genel_toplam'] + $fark);
                $kalemler[$sonIndex]['baz_satir_genel_toplam'] = $kalemler[$sonIndex]['satir_genel_toplam'];
                $kalemler[$sonIndex]['toplam'] = $kalemler[$sonIndex]['satir_genel_toplam'];
                $araToplam = $this->paraYuvarla((float) collect($kalemler)->sum('net_tutar'));
                $genelToplam = $this->paraYuvarla((float) collect($kalemler)->sum('satir_genel_toplam'));
            }
        }

        return [
            'kalemler' => $kalemler,
            'toplamlar' => [
                'ara_toplam' => $araToplam,
                'toplam_indirim' => $toplamIndirim,
                'kdv_toplam' => $kdvToplam,
                'genel_toplam' => $genelToplam,
            ],
        ];
    }

    private function proformaBelgeNoUret(int $firmaId): string
    {
        $prefix = 'PRF-'.now()->format('Ymd').'-';
        $sonBelgeNo = Fatura::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('tur', FaturaTuru::Proforma->value)
            ->where('belge_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('belge_no');

        $sira = 1;
        if (is_string($sonBelgeNo) && str_starts_with($sonBelgeNo, $prefix)) {
            $sira = ((int) substr($sonBelgeNo, strlen($prefix))) + 1;
        }

        return $prefix.str_pad((string) $sira, 6, '0', STR_PAD_LEFT);
    }

    private function paraYuvarla(float $tutar): float
    {
        return round($tutar, self::PARA_BASAMAK);
    }
}
