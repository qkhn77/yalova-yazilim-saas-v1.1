<?php

namespace App\TeknikServis\Servisler;

use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\CariHareketi;
use App\Models\Muhasebe\AlacakPlani;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Models\TeknikServis\TeknikServisMuhasebeBaglantisi;
use App\Models\TeknikServis\TeknikServisTahsilati;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Enumlar\CariHareketDurumu;
use App\Muhasebe\Servisler\AlacakPlanServisi;
use App\Muhasebe\Servisler\FinansHareketServisi;
use App\TeknikServis\Enumlar\OdemeDurumu;
use App\TeknikServis\Enumlar\TeknikServisMuhasebeIslemTipi;
use App\TeknikServis\Enumlar\TeknikServisMuhasebeSenkronDurumu;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TeknikServisTahsilatServisi
{
    public function __construct(
        private readonly FinansHareketServisi $finansHareketServisi,
    ) {}

    /**
     * @param array<string,mixed> $data
     */
    public function olustur(TeknikServisKaydi $servis, array $data): TeknikServisTahsilati|AlacakPlani
    {
        if ($this->vadeliOdemeMi((string) ($data['kanal'] ?? ''))) {
            return $this->vadeliPlanOlustur($servis, $data);
        }

        return DB::transaction(function () use ($servis, $data): TeknikServisTahsilati {
            $normalize = $this->normalize($servis, $data);
            $sonuc = $this->finansKaydiniOlustur($servis, $normalize);

            /** @var TeknikServisTahsilati $tahsilat */
            $tahsilat = TeknikServisTahsilati::query()->create(array_merge($normalize, [
                'firma_id' => (int) $servis->firma_id,
                'teknik_servis_kaydi_id' => (int) $servis->getKey(),
                'satis_faturasi_id' => $normalize['satis_faturasi_id'],
                'finans_hareketi_id' => (int) $sonuc['finans']->getKey(),
                'durum' => 'aktif',
                'olusturan_id' => Auth::id(),
                'guncelleyen_id' => Auth::id(),
            ]));

            $this->muhasebeBaglantisiniGuncelle($servis, $tahsilat, (int) $sonuc['finans']->getKey());
            $this->servisTahsilatOzetiniGuncelle($servis);

            return $tahsilat->fresh(['finansHareketi', 'satisFaturasi']) ?? $tahsilat;
        });
    }

    /**
     * @param array<string,mixed> $data
     */
    public function guncelle(TeknikServisTahsilati $tahsilat, array $data): TeknikServisTahsilati
    {
        return DB::transaction(function () use ($tahsilat, $data): TeknikServisTahsilati {
            $servis = $tahsilat->teknikServisKaydi()->firstOrFail();
            $eskiFinans = $tahsilat->finansHareketi;
            $iptalFinansId = $tahsilat->iptal_finans_hareketi_id;
            $eskiTutar = $eskiFinans instanceof FinansHareketi ? (string) $eskiFinans->tutar : (string) $tahsilat->tutar;

            if ($eskiFinans instanceof FinansHareketi && (string) ($eskiFinans->durum->value ?? $eskiFinans->durum) === 'aktif') {
                $ters = $this->finansHareketServisi->tersKayitOlustur($eskiFinans, 'Teknik servis tahsilat düzeltmesi');
                $iptalFinansId = (int) $ters->getKey();

                // Cari ters kayıtları aktif bakiyeye tekrar eklendiğinde,
                // kaynak hareket zaten iptal edildiği için tutarı iki kez
                // düşürür. Düzeltme audit izi korunur, ancak bakiye hesabına
                // yalnızca yeni tahsilat hareketi dahil edilir.
                CariHareketi::query()
                    ->where('firma_id', (int) $servis->firma_id)
                    ->where('belge_id', (int) $eskiFinans->getKey())
                    ->where('belge_turu', 'tahsilat')
                    ->where('durum', CariHareketDurumu::Aktif)
                    ->update(['durum' => CariHareketDurumu::Iptal]);
            }

            $normalize = $this->normalize($servis, $data);
            $duzeltmeNotu = sprintf(
                'Tahsilat düzeltmesi | Eski tutar: %s %s | Yeni tutar: %s %s | Düzenleme zamanı: %s.',
                number_format((float) $eskiTutar, 2, ',', '.'),
                (string) ($tahsilat->kaynak_para_birimi ?: 'TRY'),
                number_format((float) $normalize['tutar'], 2, ',', '.'),
                (string) ($normalize['kaynak_para_birimi'] ?: 'TRY'),
                now()->format('d.m.Y H:i'),
            );
            $normalize['aciklama'] = trim((string) $normalize['aciklama']) . ' | ' . $duzeltmeNotu;
            $sonuc = $this->finansKaydiniOlustur($servis, $normalize);

            if ($eskiFinans instanceof FinansHareketi) {
                $sonuc['finans']->update(['duzeltme_kaynagi_id' => (int) $eskiFinans->getKey()]);
            }

            $tahsilat->update(array_merge($normalize, [
                'satis_faturasi_id' => $normalize['satis_faturasi_id'],
                'finans_hareketi_id' => (int) $sonuc['finans']->getKey(),
                'iptal_finans_hareketi_id' => $iptalFinansId,
                'durum' => 'aktif',
                'guncelleyen_id' => Auth::id(),
            ]));

            $this->muhasebeBaglantisiniGuncelle($servis, $tahsilat, (int) $sonuc['finans']->getKey());
            $this->servisTahsilatOzetiniGuncelle($servis);

            return $tahsilat->fresh(['finansHareketi', 'satisFaturasi']) ?? $tahsilat;
        });
    }

    public function iptalEt(TeknikServisTahsilati $tahsilat, ?string $aciklama = null): TeknikServisTahsilati
    {
        return DB::transaction(function () use ($tahsilat, $aciklama): TeknikServisTahsilati {
            $servis = $tahsilat->teknikServisKaydi()->firstOrFail();
            $finans = $tahsilat->finansHareketi;
            $iptalFinansId = $tahsilat->iptal_finans_hareketi_id;

            if ($finans instanceof FinansHareketi && (string) ($finans->durum->value ?? $finans->durum) === 'aktif') {
                $ters = $this->finansHareketServisi->tersKayitOlustur($finans, $aciklama ?: 'Teknik servis tahsilatı iptal edildi');
                $iptalFinansId = (int) $ters->getKey();
            }

            $tahsilat->update([
                'durum' => 'iptal',
                'iptal_finans_hareketi_id' => $iptalFinansId,
                'guncelleyen_id' => Auth::id(),
            ]);

            $this->muhasebeBaglantisiniGuncelle($servis, $tahsilat, $tahsilat->finans_hareketi_id ? (int) $tahsilat->finans_hareketi_id : null);
            $this->servisTahsilatOzetiniGuncelle($servis);

            return $tahsilat->fresh(['finansHareketi', 'iptalFinansHareketi']) ?? $tahsilat;
        });
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function normalize(TeknikServisKaydi $servis, array $data): array
    {
        $kanal = (string) ($data['kanal'] ?? $data['tahsilat_kanali'] ?? '');
        $hesapId = match ($kanal) {
            'kasa' => (int) ($data['kasa_hesap_id'] ?? $data['tahsilat_kasa_hesap_id'] ?? 0),
            'banka' => (int) ($data['banka_hesap_id'] ?? $data['tahsilat_banka_hesap_id'] ?? 0),
            'pos' => (int) ($data['pos_hesap_id'] ?? $data['tahsilat_pos_hesap_id'] ?? 0),
            default => 0,
        };

        if (! in_array($kanal, ['kasa', 'banka', 'pos'], true) || $hesapId < 1) {
            throw new IsKuraliIstisnasi('Tahsilat kanalı ve ilgili hesap seçimi zorunludur.');
        }

        $cariId = (int) ($servis->cari_id ?? 0);
        if ($cariId < 1) {
            throw new IsKuraliIstisnasi('Tahsilat kaydı için servis kaydında cari seçili olmalıdır.');
        }

        $tutar = number_format((float) ($data['tutar'] ?? $data['tahsilat_tutari'] ?? 0), 2, '.', '');
        if ((float) $tutar <= 0) {
            throw new IsKuraliIstisnasi('Tahsilat tutarı sıfırdan büyük olmalıdır.');
        }

        $kaynakPb = strtoupper((string) ($data['kaynak_para_birimi'] ?? $data['tahsilat_para_birimi'] ?? $servis->cari?->para_birimi ?? 'TRY'));
        $hedefPb = strtoupper((string) ($data['hedef_para_birimi'] ?? $data['tahsilat_hedef_para_birimi'] ?? $kaynakPb));
        $kur = filled($data['doviz_kuru'] ?? $data['tahsilat_doviz_kuru'] ?? null)
            ? number_format((float) ($data['doviz_kuru'] ?? $data['tahsilat_doviz_kuru']), 8, '.', '')
            : null;
        $hedefTutar = filled($data['hedef_tutar'] ?? $data['tahsilat_hedef_tutar'] ?? null)
            ? number_format((float) ($data['hedef_tutar'] ?? $data['tahsilat_hedef_tutar']), 2, '.', '')
            : null;
        $tarih = Carbon::parse((string) ($data['tarih'] ?? $data['tahsilat_tarihi'] ?? now()->format('Y-m-d H:i:s')));
        $aciklama = trim((string) ($data['aciklama'] ?? $data['tahsilat_aciklama'] ?? ''));
        if ($aciklama === '') {
            $aciklama = 'Teknik servis #'.(int) $servis->getKey().' tahsilatı';
        }

        $satisFaturasiId = $this->satisFaturasiIdBul($servis);

        return [
            'kanal' => $kanal,
            'kasa_hesap_id' => $kanal === 'kasa' ? $hesapId : null,
            'banka_hesap_id' => $kanal === 'banka' ? $hesapId : null,
            'pos_hesap_id' => $kanal === 'pos' ? $hesapId : null,
            'kaynak_para_birimi' => $kaynakPb,
            'hedef_para_birimi' => $hedefPb,
            'doviz_kuru_turu' => (string) ($data['doviz_kuru_turu'] ?? $data['tahsilat_doviz_kuru_turu'] ?? 'otomatik'),
            'doviz_kuru' => $kur,
            'tutar' => $tutar,
            'hedef_tutar' => $hedefTutar,
            'tarih' => $tarih,
            'aciklama' => $aciklama,
            'satis_faturasi_id' => $satisFaturasiId,
            'cari_id' => $cariId,
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    private function vadeliPlanOlustur(TeknikServisKaydi $servis, array $data): AlacakPlani
    {
        return DB::transaction(function () use ($servis, $data): AlacakPlani {
            $kanal = (string) ($data['kanal'] ?? '');
            if (! $this->vadeliOdemeMi($kanal)) {
                throw new IsKuraliIstisnasi('Geçersiz vadeli ödeme türü.');
            }

            if ((int) ($servis->cari_id ?? 0) < 1) {
                throw new IsKuraliIstisnasi('Veresiye veya taksitli işlem için servis kaydında cari seçili olmalıdır.');
            }

            $toplamTutar = number_format((float) ($data['toplam_tutar'] ?? $data['tutar'] ?? $servis->toplam_tutar ?? 0), 2, '.', '');
            if ((float) $toplamTutar <= 0) {
                throw new IsKuraliIstisnasi('Ödeme planı tutarı sıfırdan büyük olmalıdır.');
            }

            if ($this->aktifVadeliPlanVarMi($servis)) {
                throw new IsKuraliIstisnasi('Bu servis kaydı için aktif bir ödeme planı zaten var. İşlemleri Finans > Vade Takibi ekranından yönetin.');
            }

            $pesinatTutari = number_format(max(0, (float) ($data['pesinat_tutari'] ?? 0)), 2, '.', '');
            if (bccomp($pesinatTutari, $toplamTutar, 2) === 1) {
                throw new IsKuraliIstisnasi('Peşinat toplam tutardan büyük olamaz.');
            }

            $plan = app(AlacakPlanServisi::class)->teknikServisIcinOlustur($servis->fresh(['cari', 'tahsilatlar']) ?? $servis, [
                'plan_turu' => $kanal === 'taksitli' ? 'taksit' : 'veresiye',
                'toplam_tutar' => $toplamTutar,
                'pesinat_tutari' => $pesinatTutari,
                'vade_farki_uygula' => (bool) ($data['vade_farki_uygula'] ?? false),
                'vade_farki_tipi' => $kanal === 'veresiye' ? 'tek_seferlik' : (string) (($data['vade_farki_tipi'] ?? null) ?: 'aylik'),
                'vade_farki_orani' => (string) ($data['vade_farki_orani'] ?? 0),
                'vade_farki_tutari' => (string) ($data['vade_farki_tutari'] ?? 0),
                'para_birimi' => strtoupper((string) ($data['plan_para_birimi'] ?? $data['kaynak_para_birimi'] ?? $data['para_birimi'] ?? $servis->cari?->para_birimi ?? 'TRY')),
                'ilk_vade_tarihi' => $data['ilk_vade_tarihi'] ?? $data['vade_tarihi'] ?? now()->addDays(30)->toDateString(),
                'taksit_sayisi' => $kanal === 'taksitli' ? max(2, (int) ($data['taksit_sayisi'] ?? 2)) : 1,
                'taksit_araligi_gun' => max(1, (int) ($data['taksit_araligi_gun'] ?? 30)),
            ]);

            if (bccomp($pesinatTutari, '0.00', 2) === 1) {
                $pesinatKanali = (string) ($data['pesinat_kanali'] ?? 'kasa');
                $this->pesinatTahsilatiOlustur($servis, $data, $pesinatKanali, $pesinatTutari, (int) $plan->getKey());
            } else {
                $this->servisTahsilatOzetiniGuncelle($servis);
            }

            return $plan->fresh(['taksitler']) ?? $plan;
        });
    }

    /**
     * @param array<string,mixed> $data
     */
    private function pesinatTahsilatiOlustur(TeknikServisKaydi $servis, array $data, string $kanal, string $tutar, int $planId): TeknikServisTahsilati
    {
        $pesinatVerisi = $data;
        $pesinatVerisi['kanal'] = $kanal;
        $pesinatVerisi['tutar'] = $tutar;
        $pesinatVerisi['kaynak_para_birimi'] = strtoupper((string) ($data['plan_para_birimi'] ?? $data['kaynak_para_birimi'] ?? $servis->cari?->para_birimi ?? 'TRY'));
        $pesinatVerisi['hedef_para_birimi'] = strtoupper((string) ($data['pesinat_hedef_para_birimi'] ?? $pesinatVerisi['kaynak_para_birimi']));
        $pesinatVerisi['doviz_kuru_turu'] = (string) ($data['pesinat_doviz_kuru_turu'] ?? $data['doviz_kuru_turu'] ?? 'otomatik');
        $pesinatVerisi['doviz_kuru'] = $data['pesinat_doviz_kuru'] ?? $data['doviz_kuru'] ?? null;
        $pesinatVerisi['hedef_tutar'] = $data['pesinat_hedef_tutar'] ?? $data['hedef_tutar'] ?? null;
        $pesinatVerisi['aciklama'] = trim((string) ($data['aciklama'] ?? '')) ?: 'Teknik servis #'.(int) $servis->getKey().' peşinat tahsilatı';

        if ($kanal === 'kasa') {
            $pesinatVerisi['kasa_hesap_id'] = $data['pesinat_kasa_hesap_id'] ?? $data['kasa_hesap_id'] ?? null;
        } elseif ($kanal === 'banka') {
            $pesinatVerisi['banka_hesap_id'] = $data['pesinat_banka_hesap_id'] ?? $data['banka_hesap_id'] ?? null;
        } elseif ($kanal === 'pos') {
            $pesinatVerisi['pos_hesap_id'] = $data['pesinat_pos_hesap_id'] ?? $data['pos_hesap_id'] ?? null;
        }

        $normalize = $this->normalize($servis, $pesinatVerisi);
        $sonuc = $this->finansKaydiniOlustur($servis, $normalize);

        /** @var TeknikServisTahsilati $tahsilat */
        $tahsilat = TeknikServisTahsilati::query()->create(array_merge($normalize, [
            'firma_id' => (int) $servis->firma_id,
            'teknik_servis_kaydi_id' => (int) $servis->getKey(),
            'satis_faturasi_id' => $normalize['satis_faturasi_id'],
            'finans_hareketi_id' => (int) $sonuc['finans']->getKey(),
            'durum' => 'aktif',
            'olusturan_id' => Auth::id(),
            'guncelleyen_id' => Auth::id(),
        ]));

        $this->muhasebeBaglantisiniGuncelle($servis, $tahsilat, (int) $sonuc['finans']->getKey());
        $this->servisTahsilatOzetiniGuncelle($servis);

        return $tahsilat;
    }

    private function vadeliOdemeMi(string $kanal): bool
    {
        return in_array($kanal, ['veresiye', 'taksitli'], true);
    }

    private function aktifVadeliPlanVarMi(TeknikServisKaydi $servis): bool
    {
        return AlacakPlani::query()
            ->where('firma_id', (int) $servis->firma_id)
            ->where('kaynak_turu', 'teknik_servis')
            ->where('kaynak_id', (int) $servis->getKey())
            ->whereIn('durum', ['aktif', 'kismi_odendi', 'gecikti'])
            ->exists();
    }

    /**
     * @param array<string,mixed> $normalize
     * @return array{finans: FinansHareketi}
     */
    private function finansKaydiniOlustur(TeknikServisKaydi $servis, array $normalize): array
    {
        $referansTuru = 'teknik_servis';
        $referansId = (int) $servis->getKey();
        $satisFaturasi = null;
        if (! empty($normalize['satis_faturasi_id'])) {
            $satisFaturasi = Fatura::query()->find((int) $normalize['satis_faturasi_id']);
        }
        if ($satisFaturasi && $satisFaturasi->tur->kayitUretirMi() && $satisFaturasi->durum === \App\Muhasebe\Enumlar\FaturaDurumu::Onayli) {
            $referansTuru = 'fatura';
            $referansId = (int) $satisFaturasi->getKey();
        }
        $firmaId = (int) $servis->firma_id;
        $cariId = (int) $normalize['cari_id'];
        $hesapId = (int) ($normalize[$normalize['kanal'].'_hesap_id'] ?? 0);
        $kaynakPb = (string) $normalize['kaynak_para_birimi'];
        $hedefPb = (string) ($normalize['hedef_para_birimi'] ?: $kaynakPb);
        $tutar = (string) $normalize['tutar'];
        $tarih = $normalize['tarih'];
        $aciklama = (string) $normalize['aciklama'];

        if ($kaynakPb === $hedefPb) {
            return match ((string) $normalize['kanal']) {
                'kasa' => $this->finansHareketServisi->tahsilatKasadanKaydet($firmaId, $cariId, $hesapId, $tutar, $kaynakPb, $tarih, $aciklama, $referansTuru, $referansId),
                'banka' => $this->finansHareketServisi->tahsilatBankadanKaydet($firmaId, $cariId, $hesapId, $tutar, $kaynakPb, $tarih, $aciklama, $referansTuru, $referansId),
                'pos' => $this->finansHareketServisi->tahsilatPosKaydet($firmaId, $cariId, $hesapId, $tutar, $kaynakPb, $tarih, $aciklama, $referansTuru, $referansId),
                default => throw new IsKuraliIstisnasi('Geçersiz tahsilat kanalı.'),
            };
        }

        $kur = (string) ($normalize['doviz_kuru'] ?? '0');
        if ((float) $kur <= 0) {
            throw new IsKuraliIstisnasi('Farklı para birimlerinde tahsilat için kur bilgisi zorunludur.');
        }

        $hedefTutar = (string) ($normalize['hedef_tutar'] ?? '0');
        if ((float) $hedefTutar <= 0) {
            $hedefTutar = ($kaynakPb === 'TRY' && $hedefPb !== 'TRY')
                ? number_format((float) bcdiv($tutar, $kur, 2), 2, '.', '')
                : number_format((float) bcmul($tutar, $kur, 2), 2, '.', '');
        }

        return $this->finansHareketServisi->tahsilatKurIleKaydet(
            $firmaId,
            $cariId,
            (string) $normalize['kanal'],
            $hesapId,
            $tutar,
            $kaynakPb,
            $hedefTutar,
            $hedefPb,
            $kur,
            $tarih,
            $aciklama,
            $referansTuru,
            $referansId,
        );
    }

    private function satisFaturasiIdBul(TeknikServisKaydi $servis): ?int
    {
        $baglanti = TeknikServisMuhasebeBaglantisi::query()
            ->where('firma_id', (int) $servis->firma_id)
            ->where('teknik_servis_kaydi_id', (int) $servis->getKey())
            ->where('islem_tipi', TeknikServisMuhasebeIslemTipi::Satis->value)
            ->whereNotNull('satis_faturasi_id')
            ->orderByDesc('id')
            ->first();

        if ($baglanti?->satis_faturasi_id) {
            return (int) $baglanti->satis_faturasi_id;
        }

        return null;
    }

    private function muhasebeBaglantisiniGuncelle(TeknikServisKaydi $servis, TeknikServisTahsilati $tahsilat, ?int $finansHareketiId): void
    {
        TeknikServisMuhasebeBaglantisi::query()->updateOrCreate(
            [
                'firma_id' => (int) $servis->firma_id,
                'idempotency_key' => 'teknik_servis:'.(int) $servis->getKey().':tahsilat_satiri:'.(int) $tahsilat->getKey(),
            ],
            [
                'teknik_servis_kaydi_id' => (int) $servis->getKey(),
                'islem_tipi' => TeknikServisMuhasebeIslemTipi::Tahsilat->value,
                'satis_faturasi_id' => $tahsilat->satis_faturasi_id ? (int) $tahsilat->satis_faturasi_id : null,
                'finans_hareketi_id' => $finansHareketiId,
                'senkron_durumu' => $tahsilat->durum === 'iptal'
                    ? TeknikServisMuhasebeSenkronDurumu::Iptal->value
                    : TeknikServisMuhasebeSenkronDurumu::Basarili->value,
                'son_senkron_tarihi' => now(),
                'hata_mesaji' => null,
            ]
        );
    }

    private function servisTahsilatOzetiniGuncelle(TeknikServisKaydi $servis): void
    {
        $servis = $servis->fresh(['tahsilatlar', 'muhasebeBaglantilari']);
        if (! $servis) {
            return;
        }

        $aktifTahsilat = (string) TeknikServisTahsilati::query()
            ->where('firma_id', (int) $servis->firma_id)
            ->where('teknik_servis_kaydi_id', (int) $servis->getKey())
            ->where('durum', 'aktif')
            ->sum('tutar');

        $toplam = $this->servisToplamiBul($servis);
        $odemeDurumu = OdemeDurumu::Odenmedi->value;

        if (bccomp($aktifTahsilat, '0', 2) === 1) {
            $odemeDurumu = bccomp($aktifTahsilat, $toplam, 2) >= 0
                ? OdemeDurumu::Odendi->value
                : OdemeDurumu::Kismi->value;
        }

        $servis->update([
            'odenen_tutar' => $aktifTahsilat,
            'odeme_durumu' => $odemeDurumu,
        ]);

        if ($faturaId = $this->satisFaturasiIdBul($servis)) {
            $fatura = Fatura::query()->find($faturaId);
            if ($fatura) {
                $servis->update([
                    'toplam_tutar' => (string) ($fatura->odenecek_tutar ?? $fatura->genel_toplam ?? $toplam),
                    'odenen_tutar' => (string) ($fatura->odendi_tutari ?? $aktifTahsilat),
                ]);
            }
        }
    }

    private function servisToplamiBul(TeknikServisKaydi $servis): string
    {
        if ($faturaId = $this->satisFaturasiIdBul($servis)) {
            $fatura = Fatura::query()->find($faturaId);
            if ($fatura) {
                return (string) ($fatura->odenecek_tutar ?? $fatura->genel_toplam ?? '0');
            }
        }

        return (string) ($servis->toplam_tutar ?? '0');
    }
}
