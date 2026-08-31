<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\BankaHareketi;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\CariHareketi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\Fatura;
use App\Models\Proje\IsletmeProjesi;
use App\Models\Muhasebe\KasaHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHareketi;
use App\Models\Muhasebe\PosHesabi;
use App\Muhasebe\Enumlar\CariHareketBelgeTuru;
use App\Muhasebe\Enumlar\CariHareketDurumu;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Enumlar\FinansHareketTuru;
use App\Muhasebe\Enumlar\HareketDurumu;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Guvenlik\MuhasebeFirmaErisimDenetleyicisi;
use App\Muhasebe\Yardimcilar\FinansAuditBaglami;
use App\Services\SistemOlayServisi;
use Illuminate\Support\Facades\DB;
use Throwable;

class FinansHareketServisi
{
    public function __construct(
        private readonly CariHareketServisi $cariHareketServisi,
        private readonly MuhasebeFirmaErisimDenetleyicisi $firmaDenetleyicisi,
        private readonly PosKomisyonHesaplamaServisi $posKomisyonHesaplamaServisi,
        private readonly FaturaFinansKapamaServisi $faturaFinansKapamaServisi,
        private readonly ParaBirimiDonusumServisi $paraBirimiDonusumServisi,
        private readonly SistemOlayServisi $sistemOlayServisi,
    ) {}

    private function faturaKapamaVeOtomatikDagit(FinansHareketi $finans): void
    {
        $this->faturaFinansKapamaServisi->finansHareketiniFaturayaUygula($finans);
        $finans->refresh();
        $this->faturaFinansKapamaServisi->finansHareketSonrasiOtomatikDagitim($finans);
        $tur = $finans->tur instanceof FinansHareketTuru ? $finans->tur : FinansHareketTuru::tryFrom((string) $finans->tur);
        if ($tur === FinansHareketTuru::Tahsilat) {
            app(AlacakTahsilatServisi::class)->finansTahsilatiniPlanlaraDagit($finans);
        }
        if ($finans->referans_turu === 'teknik_servis') {
            return;
        }
        if ($finans->cari_id !== null && (int) $finans->cari_id > 0) {
            $this->faturaFinansKapamaServisi->siparisVeyaFinansSonrasiAvanslariDagit(
                (int) $finans->firma_id,
                (int) $finans->cari_id,
                (string) $finans->para_birimi,
            );
        }
    }

    private function finansKaydiOlustur(array $alanlar): FinansHareketi
    {
        $firmaId = (int) ($alanlar['firma_id'] ?? 0);
        $projeId = $alanlar['isletme_proje_id'] ?? null;
        if ($projeId === null && ($alanlar['referans_turu'] ?? null) === 'fatura' && (int) ($alanlar['referans_id'] ?? 0) > 0) {
            $projeId = Fatura::query()
                ->where('firma_id', $firmaId)
                ->whereKey((int) $alanlar['referans_id'])
                ->value('isletme_proje_id');
        }
        if ($projeId !== null && ! IsletmeProjesi::query()->where('firma_id', $firmaId)->whereKey($projeId)->exists()) {
            throw new IsKuraliIstisnasi('Finans hareketi projesi aynı firmaya ait olmalıdır.');
        }
        $tutar = (string) ($alanlar['tutar'] ?? '0');
        if (! is_numeric($tutar) || bccomp($tutar, '0', 8) <= 0) {
            throw new IsKuraliIstisnasi('Finans hareketi tutarı sıfırdan büyük olmalıdır.');
        }
        $paraBirimi = strtoupper((string) ($alanlar['para_birimi'] ?? 'TRY'));
        $tarih = $alanlar['tarih'] ?? null;

        $snapshotVar = array_key_exists('baz_tutar', $alanlar)
            && filled($alanlar['baz_tutar'])
            && filled($alanlar['baz_para_birimi'] ?? null)
            && filled($alanlar['kur'] ?? null);
        $donusum = $snapshotVar
            ? [
                'tutar' => $tutar,
                'baz_tutar' => (string) $alanlar['baz_tutar'],
                'baz_para_birimi' => strtoupper((string) $alanlar['baz_para_birimi']),
                'kur' => (string) $alanlar['kur'],
                'para_birimi' => $paraBirimi,
            ]
            : $this->paraBirimiDonusumServisi->tutariBazParaBirimineHazirla(
                $firmaId,
                $tutar,
                $paraBirimi,
                is_string($tarih) || $tarih instanceof \DateTimeInterface ? $tarih : null
            );

        // Kurla ödeme/tahsilat akışında hesap tarafı baz para birimiyse,
        // kullanıcının girdiği fiili hesap tutarı esas alınır. Aksi halde
        // finans baz tutarı güncel kur tablosundan hesaplanır ve ödeme anında
        // oluşan kur farkı kaybolur.
        if (array_key_exists('baz_tutar', $alanlar) && $alanlar['baz_tutar'] !== null) {
            $donusum['baz_tutar'] = number_format((float) $alanlar['baz_tutar'], 8, '.', '');
            $donusum['baz_para_birimi'] = strtoupper((string) ($alanlar['baz_para_birimi'] ?? config('muhasebe.coklu_para_birimi.baz_para_birimi', 'TRY')));
            $donusum['kur'] = number_format((float) ($alanlar['kur'] ?? $donusum['kur']), 8, '.', '');
        }

        return FinansHareketi::query()->create(array_merge(
            FinansAuditBaglami::otomatikFinansAlanlari(),
            $alanlar,
            [
                'isletme_proje_id' => $projeId,
                'tutar' => $donusum['tutar'],
                'para_birimi' => $donusum['para_birimi'],
                'baz_tutar' => $donusum['baz_tutar'],
                'baz_para_birimi' => $donusum['baz_para_birimi'],
                'kur' => $donusum['kur'],
            ]
        ));
    }

    /**
     * Finans listesindeki kaynaksız tahsilat/ödemeyi tersleyip aynı hesapta
     * yeni, temiz bir hareket oluşturur.
     */
    public function finansHareketiniDuzelt(
        FinansHareketi $eski,
        string $tutar,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
    ): FinansHareketi {
        if ($eski->durum !== FinansHareketDurumu::Aktif) {
            throw new IsKuraliIstisnasi('Yalnızca aktif finans hareketi düzeltilebilir.');
        }

        $tur = $eski->tur instanceof FinansHareketTuru
            ? $eski->tur
            : FinansHareketTuru::tryFrom((string) $eski->tur);
        if (! in_array($tur, [FinansHareketTuru::Tahsilat, FinansHareketTuru::Odeme], true)) {
            throw new IsKuraliIstisnasi('Yalnızca tahsilat ve ödeme hareketleri düzeltilebilir.');
        }
        if ((int) $eski->cari_id < 1) {
            throw new IsKuraliIstisnasi('Cari bağlantısı olmayan hareket düzeltilemez.');
        }
        if (trim((string) $eski->referans_turu) !== '') {
            throw new IsKuraliIstisnasi('Kaynak modül hareketi kendi modülünden düzeltilmelidir.');
        }

        $hesaplar = collect([
            ['tip' => 'kasa', 'hareket' => $eski->kasaHareketleri()->where('durum', HareketDurumu::Aktif)->first()],
            ['tip' => 'banka', 'hareket' => $eski->bankaHareketleri()->where('durum', HareketDurumu::Aktif)->first()],
            ['tip' => 'pos', 'hareket' => $eski->posHareketleri()->where('durum', HareketDurumu::Aktif)->first()],
        ])->filter(fn (array $satir): bool => $satir['hareket'] !== null)->values();

        if ($hesaplar->count() !== 1) {
            throw new IsKuraliIstisnasi('Virman veya çok hesaplı hareketler bu ekrandan düzeltilemez.');
        }

        $hesap = $hesaplar->first();
        $hesapHareketi = $hesap['hareket'];
        $paraBirimi = strtoupper((string) ($eski->para_birimi ?: $hesapHareketi->para_birimi ?: 'TRY'));

        return DB::transaction(function () use ($eski, $tur, $tutar, $tarih, $aciklama, $hesap, $hesapHareketi, $paraBirimi): FinansHareketi {
            $this->tersKayitOlustur($eski, 'Finans hareketi düzeltme: ters kayıt');

            $sonuc = match ([$tur, $hesap['tip']]) {
                [FinansHareketTuru::Tahsilat, 'kasa'] => $this->tahsilatKasadanKaydet((int) $eski->firma_id, (int) $eski->cari_id, (int) $hesapHareketi->kasa_hesap_id, $tutar, $paraBirimi, $tarih, $aciklama),
                [FinansHareketTuru::Tahsilat, 'banka'] => $this->tahsilatBankadanKaydet((int) $eski->firma_id, (int) $eski->cari_id, (int) $hesapHareketi->banka_hesap_id, $tutar, $paraBirimi, $tarih, $aciklama),
                [FinansHareketTuru::Tahsilat, 'pos'] => $this->tahsilatPosKaydet((int) $eski->firma_id, (int) $eski->cari_id, (int) $hesapHareketi->pos_hesap_id, $tutar, $paraBirimi, $tarih, $aciklama),
                [FinansHareketTuru::Odeme, 'kasa'] => $this->odemeKasadanKaydet((int) $eski->firma_id, (int) $eski->cari_id, (int) $hesapHareketi->kasa_hesap_id, $tutar, $paraBirimi, $tarih, $aciklama),
                [FinansHareketTuru::Odeme, 'banka'] => $this->odemeBankadanKaydet((int) $eski->firma_id, (int) $eski->cari_id, (int) $hesapHareketi->banka_hesap_id, $tutar, $paraBirimi, $tarih, $aciklama),
                default => throw new IsKuraliIstisnasi('Bu hareket türü düzeltilemez.'),
            };

            /** @var FinansHareketi $yeni */
            $yeni = $sonuc['finans'];
            $yeni->update(['duzeltme_kaynagi_id' => (int) $eski->getKey()]);

            return $yeni->fresh();
        });
    }

    public function tahsilatKasadanKaydet(
        int $firmaId,
        int $cariId,
        int $kasaHesapId,
        string $tutar,
        string $paraBirimi,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
        ?string $referansTuru = null,
        ?int $referansId = null,
    ): array {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);
        $this->paraBirimiDogrula($paraBirimi);
        $cari = $this->cariyiYukleVeDogrula($firmaId, $cariId, $paraBirimi);
        $kasa = $this->kasayiYukleVeDogrula($firmaId, $kasaHesapId, $paraBirimi);

        return DB::transaction(function () use ($firmaId, $cari, $kasa, $tutar, $paraBirimi, $tarih, $aciklama, $referansTuru, $referansId): array {
            $finans = $this->finansKaydiOlustur([
                'firma_id' => $firmaId,
                'tur' => FinansHareketTuru::Tahsilat,
                'tarih' => $tarih,
                'vade_tarihi' => null,
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'cari_id' => $cari->getKey(),
                'aciklama' => $aciklama,
                'referans_turu' => $referansTuru,
                'referans_id' => $referansId,
                'durum' => FinansHareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            $kasaHareket = KasaHareketi::query()->create([
                'firma_id' => $firmaId,
                'finans_hareket_id' => $finans->getKey(),
                'kasa_hesap_id' => $kasa->getKey(),
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'durum' => HareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            $cariAlanlari = [
                'cari_id' => (int) $cari->getKey(),
                'belge_turu' => CariHareketBelgeTuru::Tahsilat,
                'belge_id' => (int) $finans->getKey(),
                'islem_tarihi' => $tarih,
                'borc' => $tutar,
                'alacak' => '0',
                'para_birimi' => $paraBirimi,
                'aciklama' => $aciklama,
            ];
            $cariHareket = $this->cariHareketServisi->kayitOlustur($firmaId, $cariAlanlari);

            $this->faturaKapamaVeOtomatikDagit($finans);

            return ['finans' => $finans, 'kasa' => $kasaHareket, 'cari' => $cariHareket];
        });
    }

    /**
     * Çek giriş/çıkışında yalnızca cari ve finans hareketi üretir.
     * Kasa, banka veya POS hareketi oluşturmaz.
     *
     * @return array{finans: FinansHareketi, cari: CariHareketi}
     */
    public function cekCariHareketiKaydet(
        int $firmaId,
        int $cariId,
        string $tutar,
        string $paraBirimi,
        \DateTimeInterface|string $tarih,
        \DateTimeInterface|string|null $vadeTarihi,
        FinansHareketTuru $tur,
        int $cekId,
        ?string $aciklama = null,
    ): array {
        if (! in_array($tur, [FinansHareketTuru::Tahsilat, FinansHareketTuru::Odeme], true)) {
            throw new IsKuraliIstisnasi('Çek finans hareketi yalnızca tahsilat veya ödeme olabilir.');
        }

        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);
        $this->paraBirimiDogrula($paraBirimi);
        $cari = $this->cariyiYukleVeDogrula($firmaId, $cariId, $paraBirimi);

        return DB::transaction(function () use ($firmaId, $cari, $tutar, $paraBirimi, $tarih, $vadeTarihi, $tur, $cekId, $aciklama): array {
            $finansAlanlari = [
                'firma_id' => $firmaId,
                'tur' => $tur,
                'tarih' => $tarih,
                'vade_tarihi' => $vadeTarihi,
                'tutar' => $tutar,
                'para_birimi' => strtoupper($paraBirimi),
                'cari_id' => $cari->getKey(),
                'aciklama' => $aciklama,
                'referans_turu' => 'cek',
                'referans_id' => $cekId,
                'durum' => FinansHareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ];
            $finans = $this->finansKaydiOlustur($finansAlanlari);

            $cariAlanlari = [
                'cari_id' => (int) $cari->getKey(),
                'belge_turu' => $tur === FinansHareketTuru::Tahsilat
                    ? CariHareketBelgeTuru::Tahsilat
                    : CariHareketBelgeTuru::Odeme,
                'belge_id' => (int) $finans->getKey(),
                'islem_tarihi' => $tarih,
                'vade_tarihi' => $vadeTarihi,
                'borc' => $tur === FinansHareketTuru::Tahsilat ? $tutar : '0',
                'alacak' => $tur === FinansHareketTuru::Odeme ? $tutar : '0',
                'para_birimi' => strtoupper($paraBirimi),
                'aciklama' => $aciklama,
            ];
            $cariHareket = $this->cariHareketServisi->kayitOlustur($firmaId, $cariAlanlari);

            $this->faturaKapamaVeOtomatikDagit($finans);

            return ['finans' => $finans, 'cari' => $cariHareket];
        });
    }

    /**
     * E-ticaret sipariş tahsilatı (oturumsuz ödeme sonrası). Kimlik denetimi firma varlığı ile sınırlıdır.
     *
     * @return array{finans: FinansHareketi, kasa: KasaHareketi, cari: CariHareketi}
     */
    public function tahsilatKasadanEcommerceKaydet(
        int $firmaId,
        int $cariId,
        int $kasaHesapId,
        string $tutar,
        string $paraBirimi,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
        ?string $referansTuru = null,
        ?int $referansId = null,
    ): array {
        $this->firmaDenetleyicisi->eTicaretYazmaIcinFirmaKontrolEt($firmaId);
        $this->paraBirimiDogrula($paraBirimi);
        $cari = $this->cariyiYukleVeDogrula($firmaId, $cariId, $paraBirimi, eticaretKapsaminda: true);
        $kasa = $this->kasayiYukleVeDogrula($firmaId, $kasaHesapId, $paraBirimi);

        return DB::transaction(function () use ($firmaId, $cari, $kasa, $tutar, $paraBirimi, $tarih, $aciklama, $referansTuru, $referansId): array {
            $finansAlanlari = [
                'firma_id' => $firmaId,
                'tur' => FinansHareketTuru::Tahsilat,
                'tarih' => $tarih,
                'vade_tarihi' => null,
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'cari_id' => $cari->getKey(),
                'aciklama' => $aciklama,
                'referans_turu' => $referansTuru,
                'referans_id' => $referansId,
                'durum' => FinansHareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ];
            $finans = $this->finansKaydiOlustur($finansAlanlari);

            $kasaHareket = KasaHareketi::query()->create([
                'firma_id' => $firmaId,
                'finans_hareket_id' => $finans->getKey(),
                'kasa_hesap_id' => $kasa->getKey(),
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'durum' => HareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            $cariHareket = $this->cariHareketServisi->kayitOlustur($firmaId, [
                'cari_id' => (int) $cari->getKey(),
                'belge_turu' => CariHareketBelgeTuru::Tahsilat,
                'belge_id' => (int) $finans->getKey(),
                'islem_tarihi' => $tarih,
                'borc' => $tutar,
                'alacak' => '0',
                'para_birimi' => $paraBirimi,
                'aciklama' => $aciklama,
            ], eTicaretSistemCagrisi: true);

            $this->faturaKapamaVeOtomatikDagit($finans);

            return ['finans' => $finans, 'kasa' => $kasaHareket, 'cari' => $cariHareket];
        });
    }

    public function odemeKasadanKaydet(
        int $firmaId,
        int $cariId,
        int $kasaHesapId,
        string $tutar,
        string $paraBirimi,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
        ?string $referansTuru = null,
        ?int $referansId = null,
    ): array {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);
        $this->paraBirimiDogrula($paraBirimi);
        $cari = $this->cariyiYukleVeDogrula($firmaId, $cariId, $paraBirimi);
        $kasa = $this->kasayiYukleVeDogrula($firmaId, $kasaHesapId, $paraBirimi);

        $negatif = bcmul($tutar, '-1', 2);

        return DB::transaction(function () use ($firmaId, $cari, $kasa, $tutar, $negatif, $paraBirimi, $tarih, $aciklama, $referansTuru, $referansId): array {
            $finansAlanlari = [
                'firma_id' => $firmaId,
                'tur' => FinansHareketTuru::Odeme,
                'tarih' => $tarih,
                'vade_tarihi' => null,
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'cari_id' => $cari->getKey(),
                'aciklama' => $aciklama,
                'referans_turu' => $referansTuru,
                'referans_id' => $referansId,
                'durum' => FinansHareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ];
            $finans = $this->finansKaydiOlustur($finansAlanlari);

            $kasaHareket = KasaHareketi::query()->create([
                'firma_id' => $firmaId,
                'finans_hareket_id' => $finans->getKey(),
                'kasa_hesap_id' => $kasa->getKey(),
                'tutar' => $negatif,
                'para_birimi' => $paraBirimi,
                'durum' => HareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            $cariHareket = $this->cariHareketServisi->kayitOlustur($firmaId, [
                'cari_id' => (int) $cari->getKey(),
                'belge_turu' => CariHareketBelgeTuru::Odeme,
                'belge_id' => (int) $finans->getKey(),
                'islem_tarihi' => $tarih,
                'borc' => '0',
                'alacak' => $tutar,
                'para_birimi' => $paraBirimi,
                'aciklama' => $aciklama,
            ]);

            $this->faturaKapamaVeOtomatikDagit($finans);

            return ['finans' => $finans, 'kasa' => $kasaHareket, 'cari' => $cariHareket];
        });
    }

    /**
     * @return array{finans: FinansHareketi, banka: BankaHareketi, cari: CariHareketi}
     */
    public function tahsilatBankadanKaydet(
        int $firmaId,
        int $cariId,
        int $bankaHesapId,
        string $tutar,
        string $paraBirimi,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
        ?string $referansTuru = null,
        ?int $referansId = null,
        ?string $dekontNo = null,
        ?string $islemReferansi = null,
        ?string $bankaDetayAciklama = null,
    ): array {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);
        $this->paraBirimiDogrula($paraBirimi);
        $cari = $this->cariyiYukleVeDogrula($firmaId, $cariId, $paraBirimi);
        $banka = $this->bankayiYukleVeDogrula($firmaId, $bankaHesapId, $paraBirimi);
        $ekAciklama = $this->bankaPosEkMetniOlustur($aciklama, $dekontNo, $islemReferansi, $bankaDetayAciklama);

        return DB::transaction(function () use ($firmaId, $cari, $banka, $tutar, $paraBirimi, $tarih, $aciklama, $referansTuru, $referansId, $ekAciklama, $dekontNo, $islemReferansi, $bankaDetayAciklama): array {
            $finans = $this->finansKaydiOlustur([
                'firma_id' => $firmaId,
                'tur' => FinansHareketTuru::Tahsilat,
                'tarih' => $tarih,
                'vade_tarihi' => null,
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'cari_id' => $cari->getKey(),
                'aciklama' => $aciklama,
                'ek_aciklama' => $ekAciklama,
                'referans_turu' => $referansTuru,
                'referans_id' => $referansId,
                'durum' => FinansHareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            $bankaHareket = BankaHareketi::query()->create([
                'firma_id' => $firmaId,
                'finans_hareket_id' => $finans->getKey(),
                'banka_hesap_id' => $banka->getKey(),
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'dekont_no' => $dekontNo,
                'islem_referansi' => $islemReferansi,
                'detay_aciklama' => $bankaDetayAciklama,
                'durum' => HareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            $cariHareket = $this->cariHareketServisi->kayitOlustur($firmaId, [
                'cari_id' => (int) $cari->getKey(),
                'belge_turu' => CariHareketBelgeTuru::Tahsilat,
                'belge_id' => (int) $finans->getKey(),
                'islem_tarihi' => $tarih,
                'borc' => $tutar,
                'alacak' => '0',
                'para_birimi' => $paraBirimi,
                'aciklama' => $aciklama,
            ]);

            $this->faturaKapamaVeOtomatikDagit($finans);

            return ['finans' => $finans, 'banka' => $bankaHareket, 'cari' => $cariHareket];
        });
    }

    /**
     * E-ticaret sipariş tahsilatı için banka hareketi oluşturur.
     *
     * @return array{finans: FinansHareketi, banka: BankaHareketi, cari: CariHareketi}
     */
    public function tahsilatBankadanEcommerceKaydet(
        int $firmaId,
        int $cariId,
        int $bankaHesapId,
        string $tutar,
        string $paraBirimi,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
        ?string $referansTuru = null,
        ?int $referansId = null,
        ?string $dekontNo = null,
        ?string $islemReferansi = null,
        ?string $bankaDetayAciklama = null,
    ): array {
        $this->firmaDenetleyicisi->eTicaretYazmaIcinFirmaKontrolEt($firmaId);
        $this->paraBirimiDogrula($paraBirimi);
        $cari = $this->cariyiYukleVeDogrula($firmaId, $cariId, $paraBirimi, eticaretKapsaminda: true);
        $banka = $this->bankayiYukleVeDogrula($firmaId, $bankaHesapId, $paraBirimi);
        $ekAciklama = $this->bankaPosEkMetniOlustur($aciklama, $dekontNo, $islemReferansi, $bankaDetayAciklama);

        return DB::transaction(function () use ($firmaId, $cari, $banka, $tutar, $paraBirimi, $tarih, $aciklama, $referansTuru, $referansId, $ekAciklama, $dekontNo, $islemReferansi, $bankaDetayAciklama): array {
            $finans = $this->finansKaydiOlustur([
                'firma_id' => $firmaId,
                'tur' => FinansHareketTuru::Tahsilat,
                'tarih' => $tarih,
                'vade_tarihi' => null,
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'cari_id' => $cari->getKey(),
                'aciklama' => $aciklama,
                'ek_aciklama' => $ekAciklama,
                'referans_turu' => $referansTuru,
                'referans_id' => $referansId,
                'durum' => FinansHareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            $bankaHareket = BankaHareketi::query()->create([
                'firma_id' => $firmaId,
                'finans_hareket_id' => $finans->getKey(),
                'banka_hesap_id' => $banka->getKey(),
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'dekont_no' => $dekontNo,
                'islem_referansi' => $islemReferansi,
                'detay_aciklama' => $bankaDetayAciklama,
                'durum' => HareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            $cariHareket = $this->cariHareketServisi->kayitOlustur($firmaId, [
                'cari_id' => (int) $cari->getKey(),
                'belge_turu' => CariHareketBelgeTuru::Tahsilat,
                'belge_id' => (int) $finans->getKey(),
                'islem_tarihi' => $tarih,
                'borc' => $tutar,
                'alacak' => '0',
                'para_birimi' => $paraBirimi,
                'aciklama' => $aciklama,
            ], eTicaretSistemCagrisi: true);

            $this->faturaKapamaVeOtomatikDagit($finans);

            return ['finans' => $finans, 'banka' => $bankaHareket, 'cari' => $cariHareket];
        });
    }

    /**
     * @return array{finans: FinansHareketi, banka: BankaHareketi, cari: CariHareketi}
     */
    public function odemeBankadanKaydet(
        int $firmaId,
        int $cariId,
        int $bankaHesapId,
        string $tutar,
        string $paraBirimi,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
        ?string $referansTuru = null,
        ?int $referansId = null,
        ?string $dekontNo = null,
        ?string $islemReferansi = null,
        ?string $bankaDetayAciklama = null,
    ): array {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);
        $this->paraBirimiDogrula($paraBirimi);
        $cari = $this->cariyiYukleVeDogrula($firmaId, $cariId, $paraBirimi);
        $banka = $this->bankayiYukleVeDogrula($firmaId, $bankaHesapId, $paraBirimi);
        $negatif = bcmul($tutar, '-1', 2);
        $ekAciklama = $this->bankaPosEkMetniOlustur($aciklama, $dekontNo, $islemReferansi, $bankaDetayAciklama);

        return DB::transaction(function () use ($firmaId, $cari, $banka, $tutar, $negatif, $paraBirimi, $tarih, $aciklama, $referansTuru, $referansId, $ekAciklama, $dekontNo, $islemReferansi, $bankaDetayAciklama): array {
            $finans = $this->finansKaydiOlustur([
                'firma_id' => $firmaId,
                'tur' => FinansHareketTuru::Odeme,
                'tarih' => $tarih,
                'vade_tarihi' => null,
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'cari_id' => $cari->getKey(),
                'aciklama' => $aciklama,
                'ek_aciklama' => $ekAciklama,
                'referans_turu' => $referansTuru,
                'referans_id' => $referansId,
                'durum' => FinansHareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            $bankaHareket = BankaHareketi::query()->create([
                'firma_id' => $firmaId,
                'finans_hareket_id' => $finans->getKey(),
                'banka_hesap_id' => $banka->getKey(),
                'tutar' => $negatif,
                'para_birimi' => $paraBirimi,
                'dekont_no' => $dekontNo,
                'islem_referansi' => $islemReferansi,
                'detay_aciklama' => $bankaDetayAciklama,
                'durum' => HareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            $cariHareket = $this->cariHareketServisi->kayitOlustur($firmaId, [
                'cari_id' => (int) $cari->getKey(),
                'belge_turu' => CariHareketBelgeTuru::Odeme,
                'belge_id' => (int) $finans->getKey(),
                'islem_tarihi' => $tarih,
                'borc' => '0',
                'alacak' => $tutar,
                'para_birimi' => $paraBirimi,
                'aciklama' => $aciklama,
            ]);

            $this->faturaKapamaVeOtomatikDagit($finans);

            return ['finans' => $finans, 'banka' => $bankaHareket, 'cari' => $cariHareket];
        });
    }

    /**
     * @return array{finans: FinansHareketi, pos: PosHareketi, cari: CariHareketi}
     */
    public function tahsilatPosKaydet(
        int $firmaId,
        int $cariId,
        int $posHesapId,
        string $tutar,
        string $paraBirimi,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
        ?string $referansTuru = null,
        ?int $referansId = null,
        ?string $slipNo = null,
        ?string $provizyonNo = null,
        ?string $posDetayAciklama = null,
    ): array {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);
        $this->paraBirimiDogrula($paraBirimi);
        $cari = $this->cariyiYukleVeDogrula($firmaId, $cariId, $paraBirimi);
        $pos = $this->posuYukleVeDogrula($firmaId, $posHesapId, $paraBirimi);
        $ekAciklama = $this->bankaPosEkMetniOlustur($aciklama, $slipNo, $provizyonNo, $posDetayAciklama);

        return DB::transaction(function () use ($firmaId, $cari, $pos, $tutar, $paraBirimi, $tarih, $aciklama, $referansTuru, $referansId, $ekAciklama, $slipNo, $provizyonNo, $posDetayAciklama): array {
            $finans = $this->finansKaydiOlustur([
                'firma_id' => $firmaId,
                'tur' => FinansHareketTuru::Tahsilat,
                'tarih' => $tarih,
                'vade_tarihi' => null,
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'cari_id' => $cari->getKey(),
                'aciklama' => $aciklama,
                'ek_aciklama' => $ekAciklama,
                'referans_turu' => $referansTuru,
                'referans_id' => $referansId,
                'durum' => FinansHareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            $posHareket = PosHareketi::query()->create([
                'firma_id' => $firmaId,
                'finans_hareket_id' => $finans->getKey(),
                'pos_hesap_id' => $pos->getKey(),
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'slip_no' => $slipNo,
                'provizyon_no' => $provizyonNo,
                'detay_aciklama' => $posDetayAciklama,
                'durum' => HareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            $cariHareket = $this->cariHareketServisi->kayitOlustur($firmaId, [
                'cari_id' => (int) $cari->getKey(),
                'belge_turu' => CariHareketBelgeTuru::Tahsilat,
                'belge_id' => (int) $finans->getKey(),
                'islem_tarihi' => $tarih,
                'borc' => $tutar,
                'alacak' => '0',
                'para_birimi' => $paraBirimi,
                'aciklama' => $aciklama,
            ]);

            $this->faturaKapamaVeOtomatikDagit($finans);

            return ['finans' => $finans, 'pos' => $posHareket, 'cari' => $cariHareket];
        });
    }

    /**
     * E-ticaret sipariş tahsilatı için POS hareketi oluşturur.
     *
     * @return array{finans: FinansHareketi, pos: PosHareketi, cari: CariHareketi}
     */
    public function tahsilatPosEcommerceKaydet(
        int $firmaId,
        int $cariId,
        int $posHesapId,
        string $tutar,
        string $paraBirimi,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
        ?string $referansTuru = null,
        ?int $referansId = null,
        ?string $slipNo = null,
        ?string $provizyonNo = null,
        ?string $posDetayAciklama = null,
    ): array {
        $this->firmaDenetleyicisi->eTicaretYazmaIcinFirmaKontrolEt($firmaId);
        $this->paraBirimiDogrula($paraBirimi);
        $cari = $this->cariyiYukleVeDogrula($firmaId, $cariId, $paraBirimi, eticaretKapsaminda: true);
        $pos = $this->posuYukleVeDogrula($firmaId, $posHesapId, $paraBirimi);
        $ekAciklama = $this->bankaPosEkMetniOlustur($aciklama, $slipNo, $provizyonNo, $posDetayAciklama);

        return DB::transaction(function () use ($firmaId, $cari, $pos, $tutar, $paraBirimi, $tarih, $aciklama, $referansTuru, $referansId, $ekAciklama, $slipNo, $provizyonNo, $posDetayAciklama): array {
            $finans = $this->finansKaydiOlustur([
                'firma_id' => $firmaId,
                'tur' => FinansHareketTuru::Tahsilat,
                'tarih' => $tarih,
                'vade_tarihi' => null,
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'cari_id' => $cari->getKey(),
                'aciklama' => $aciklama,
                'ek_aciklama' => $ekAciklama,
                'referans_turu' => $referansTuru,
                'referans_id' => $referansId,
                'durum' => FinansHareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            $posHareket = PosHareketi::query()->create([
                'firma_id' => $firmaId,
                'finans_hareket_id' => $finans->getKey(),
                'pos_hesap_id' => $pos->getKey(),
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'slip_no' => $slipNo,
                'provizyon_no' => $provizyonNo,
                'detay_aciklama' => $posDetayAciklama,
                'durum' => HareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            $cariHareket = $this->cariHareketServisi->kayitOlustur($firmaId, [
                'cari_id' => (int) $cari->getKey(),
                'belge_turu' => CariHareketBelgeTuru::Tahsilat,
                'belge_id' => (int) $finans->getKey(),
                'islem_tarihi' => $tarih,
                'borc' => $tutar,
                'alacak' => '0',
                'para_birimi' => $paraBirimi,
                'aciklama' => $aciklama,
            ], eTicaretSistemCagrisi: true);

            $this->faturaKapamaVeOtomatikDagit($finans);

            return ['finans' => $finans, 'pos' => $posHareket, 'cari' => $cariHareket];
        });
    }

    /**
     * POS komisyonlu tahsilat: cari hareketi **brüt** slip tutarı üzerinden; POS hesabına **net** (brüt − komisyon) yansır.
     *
     * @return array{finans: FinansHareketi, pos: PosHareketi, cari: CariHareketi}
     */
    public function tahsilatPosKomisyonluKaydet(
        int $firmaId,
        int $cariId,
        int $posHesapId,
        string $brutTutar,
        string $komisyonTutari,
        string $paraBirimi,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
        ?string $referansTuru = null,
        ?int $referansId = null,
        ?string $slipNo = null,
        ?string $provizyonNo = null,
        ?string $posDetayAciklama = null,
    ): array {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);
        $this->paraBirimiDogrula($paraBirimi);
        $cari = $this->cariyiYukleVeDogrula($firmaId, $cariId, $paraBirimi);
        $pos = $this->posuYukleVeDogrula($firmaId, $posHesapId, $paraBirimi);

        if (bccomp($komisyonTutari, $brutTutar, 2) > 0) {
            throw new IsKuraliIstisnasi('POS komisyon tutarı, brüt tutardan büyük olamaz.');
        }

        $netPos = $this->posKomisyonHesaplamaServisi->netTahsilatHesapla($brutTutar, $komisyonTutari);
        $oran = bccomp($brutTutar, '0', 4) === 0
            ? null
            : bcmul(bcdiv($komisyonTutari, $brutTutar, 8), '100', 4);

        $ekAciklama = $this->bankaPosEkMetniOlustur(
            'POS komisyonlu tahsilat. Brüt: '.$brutTutar.' '.$paraBirimi.', komisyon: '.$komisyonTutari.', net POS: '.$netPos.'.',
            $slipNo,
            $provizyonNo,
            $posDetayAciklama
        );

        return DB::transaction(function () use ($firmaId, $cari, $pos, $brutTutar, $komisyonTutari, $netPos, $oran, $paraBirimi, $tarih, $aciklama, $referansTuru, $referansId, $ekAciklama, $slipNo, $provizyonNo, $posDetayAciklama): array {
            $finans = $this->finansKaydiOlustur([
                'firma_id' => $firmaId,
                'tur' => FinansHareketTuru::Tahsilat,
                'tarih' => $tarih,
                'vade_tarihi' => null,
                'tutar' => $brutTutar,
                'brut_tutar' => $brutTutar,
                'pos_komisyon_tutari' => $komisyonTutari,
                'pos_komisyon_orani_yuzde' => $oran,
                'para_birimi' => $paraBirimi,
                'cari_id' => $cari->getKey(),
                'aciklama' => $aciklama ?? ('POS tahsilat (brüt '.$brutTutar.' '.$paraBirimi.')'),
                'ek_aciklama' => $ekAciklama,
                'referans_turu' => $referansTuru,
                'referans_id' => $referansId,
                'durum' => FinansHareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            $posHareket = PosHareketi::query()->create([
                'firma_id' => $firmaId,
                'finans_hareket_id' => $finans->getKey(),
                'pos_hesap_id' => $pos->getKey(),
                'tutar' => $netPos,
                'para_birimi' => $paraBirimi,
                'brut_tutar' => $brutTutar,
                'komisyon_tutari' => $komisyonTutari,
                'slip_no' => $slipNo,
                'provizyon_no' => $provizyonNo,
                'detay_aciklama' => $posDetayAciklama,
                'durum' => HareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            $cariHareket = $this->cariHareketServisi->kayitOlustur($firmaId, [
                'cari_id' => (int) $cari->getKey(),
                'belge_turu' => CariHareketBelgeTuru::Tahsilat,
                'belge_id' => (int) $finans->getKey(),
                'islem_tarihi' => $tarih,
                'borc' => $brutTutar,
                'alacak' => '0',
                'para_birimi' => $paraBirimi,
                'aciklama' => $aciklama,
            ]);

            $this->faturaKapamaVeOtomatikDagit($finans);

            return ['finans' => $finans, 'pos' => $posHareket, 'cari' => $cariHareket];
        });
    }

    /**
     * POS iade / iptal: müşteriye yapılan iade tutarı kadar cari alacağı artar; POS hesabından tutar düşer.
     * Varsayılan referans: `pos_iade` (önceki tahsilat slip’i `referans_id` ile bağlanabilir).
     *
     * @return array{finans: FinansHareketi, pos: PosHareketi, cari: CariHareketi}
     */
    public function posIadeKaydet(
        int $firmaId,
        int $cariId,
        int $posHesapId,
        string $tutar,
        string $paraBirimi,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
        ?string $referansTuru = null,
        ?int $referansId = null,
        ?string $slipNo = null,
        ?string $provizyonNo = null,
        ?string $posDetayAciklama = null,
    ): array {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);
        $this->paraBirimiDogrula($paraBirimi);
        $cari = $this->cariyiYukleVeDogrula($firmaId, $cariId, $paraBirimi);
        $pos = $this->posuYukleVeDogrula($firmaId, $posHesapId, $paraBirimi);
        $negatif = bcmul($tutar, '-1', 2);
        $refTur = $referansTuru ?? 'pos_iade';
        $ekAciklama = $this->bankaPosEkMetniOlustur(
            'POS iade / iptal işlemi.',
            $slipNo,
            $provizyonNo,
            $posDetayAciklama
        );

        return DB::transaction(function () use ($firmaId, $cari, $pos, $tutar, $negatif, $paraBirimi, $tarih, $aciklama, $refTur, $referansId, $ekAciklama, $slipNo, $provizyonNo, $posDetayAciklama): array {
            $finans = $this->finansKaydiOlustur([
                'firma_id' => $firmaId,
                'tur' => FinansHareketTuru::Odeme,
                'tarih' => $tarih,
                'vade_tarihi' => null,
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'cari_id' => $cari->getKey(),
                'aciklama' => $aciklama ?? ('POS iade ('.$tutar.' '.$paraBirimi.')'),
                'ek_aciklama' => $ekAciklama,
                'referans_turu' => $refTur,
                'referans_id' => $referansId,
                'durum' => FinansHareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            $posHareket = PosHareketi::query()->create([
                'firma_id' => $firmaId,
                'finans_hareket_id' => $finans->getKey(),
                'pos_hesap_id' => $pos->getKey(),
                'tutar' => $negatif,
                'para_birimi' => $paraBirimi,
                'slip_no' => $slipNo,
                'provizyon_no' => $provizyonNo,
                'detay_aciklama' => $posDetayAciklama,
                'durum' => HareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            $cariHareket = $this->cariHareketServisi->kayitOlustur($firmaId, [
                'cari_id' => (int) $cari->getKey(),
                'belge_turu' => CariHareketBelgeTuru::Odeme,
                'belge_id' => (int) $finans->getKey(),
                'islem_tarihi' => $tarih,
                'borc' => '0',
                'alacak' => $tutar,
                'para_birimi' => $paraBirimi,
                'aciklama' => $aciklama,
            ]);

            $this->faturaKapamaVeOtomatikDagit($finans);

            return ['finans' => $finans, 'pos' => $posHareket, 'cari' => $cariHareket];
        });
    }

    /**
     * Hesaplar arasi virman: kaynak hesap negatif, hedef hesap pozitif.
     *
     * @return array{finans: FinansHareketi, kaynak: KasaHareketi|BankaHareketi|PosHareketi, hedef: KasaHareketi|BankaHareketi|PosHareketi}
     */
    public function virmanHesaplarArasiKaydet(
        int $firmaId,
        string $kaynakTipi,
        int $kaynakHesapId,
        string $hedefTipi,
        int $hedefHesapId,
        string $tutar,
        string $paraBirimi,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
    ): array {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);
        $this->paraBirimiDogrula($paraBirimi);

        $kaynakTipi = strtolower(trim($kaynakTipi));
        $hedefTipi = strtolower(trim($hedefTipi));
        $izinliTipler = ['kasa', 'banka', 'pos'];
        if (! in_array($kaynakTipi, $izinliTipler, true) || ! in_array($hedefTipi, $izinliTipler, true)) {
            throw new IsKuraliIstisnasi('Virman için geçerli hesap tipleri: kasa, banka, pos.');
        }
        if ($kaynakTipi === $hedefTipi && $kaynakHesapId === $hedefHesapId) {
            throw new IsKuraliIstisnasi('Kaynak ve hedef hesap aynı olamaz.');
        }

        $this->virmanHesabiYukleVeDogrula($firmaId, $kaynakTipi, $kaynakHesapId, $paraBirimi);
        $this->virmanHesabiYukleVeDogrula($firmaId, $hedefTipi, $hedefHesapId, $paraBirimi);
        $negatif = bcmul($tutar, '-1', 2);

        return DB::transaction(function () use ($firmaId, $kaynakTipi, $kaynakHesapId, $hedefTipi, $hedefHesapId, $tutar, $negatif, $paraBirimi, $tarih, $aciklama): array {
            $finans = $this->finansKaydiOlustur([
                'firma_id' => $firmaId,
                'tur' => FinansHareketTuru::Virman,
                'tarih' => $tarih,
                'vade_tarihi' => null,
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'cari_id' => null,
                'aciklama' => $aciklama,
                'referans_turu' => null,
                'referans_id' => null,
                'durum' => FinansHareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            $kaynak = $this->virmanHareketiOlustur(
                $firmaId,
                (int) $finans->getKey(),
                $kaynakTipi,
                $kaynakHesapId,
                $negatif,
                $paraBirimi
            );

            $hedef = $this->virmanHareketiOlustur(
                $firmaId,
                (int) $finans->getKey(),
                $hedefTipi,
                $hedefHesapId,
                $tutar,
                $paraBirimi
            );

            return ['finans' => $finans, 'kaynak' => $kaynak, 'hedef' => $hedef];
        });
    }

    /**
     * Farkli para birimleri arasinda virman:
     * kaynak hesap negatif (kaynak pb), hedef hesap pozitif (hedef pb).
     *
     * @return array{finans: FinansHareketi, kaynak: KasaHareketi|BankaHareketi|PosHareketi, hedef: KasaHareketi|BankaHareketi|PosHareketi}
     */
    public function virmanHesaplarArasiKurIleKaydet(
        int $firmaId,
        string $kaynakTipi,
        int $kaynakHesapId,
        string $hedefTipi,
        int $hedefHesapId,
        string $kaynakTutar,
        string $kaynakParaBirimi,
        string $hedefTutar,
        string $hedefParaBirimi,
        string $kur,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
    ): array {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);

        $kaynakParaBirimi = strtoupper(trim($kaynakParaBirimi));
        $hedefParaBirimi = strtoupper(trim($hedefParaBirimi));
        $this->paraBirimiDogrula($kaynakParaBirimi);
        $this->paraBirimiDogrula($hedefParaBirimi);

        $kaynakTipi = strtolower(trim($kaynakTipi));
        $hedefTipi = strtolower(trim($hedefTipi));
        $izinliTipler = ['kasa', 'banka', 'pos'];
        if (! in_array($kaynakTipi, $izinliTipler, true) || ! in_array($hedefTipi, $izinliTipler, true)) {
            throw new IsKuraliIstisnasi('Virman icin gecerli hesap tipleri: kasa, banka, pos.');
        }
        if ($kaynakTipi === $hedefTipi && $kaynakHesapId === $hedefHesapId) {
            throw new IsKuraliIstisnasi('Kaynak ve hedef hesap ayni olamaz.');
        }

        $kaynakTutar = number_format((float) $kaynakTutar, 2, '.', '');
        $hedefTutar = number_format((float) $hedefTutar, 2, '.', '');
        $kur = number_format((float) $kur, 8, '.', '');
        if (bccomp($kaynakTutar, '0', 2) <= 0 || bccomp($hedefTutar, '0', 2) <= 0) {
            throw new IsKuraliIstisnasi('Virman tutarlari sifirdan buyuk olmalidir.');
        }
        if ((float) $kur <= 0) {
            throw new IsKuraliIstisnasi('Kur sifirdan buyuk olmalidir.');
        }

        $this->virmanHesabiYukleVeDogrula($firmaId, $kaynakTipi, $kaynakHesapId, $kaynakParaBirimi);
        $this->virmanHesabiYukleVeDogrula($firmaId, $hedefTipi, $hedefHesapId, $hedefParaBirimi);

        $negatifKaynak = bcmul($kaynakTutar, '-1', 2);
        $aciklamaMetni = trim((string) $aciklama);
        $kurDetay = 'Kur: '.$kur.' ('.$kaynakParaBirimi.' -> '.$hedefParaBirimi.')';
        $finansAciklama = $aciklamaMetni !== '' ? ($aciklamaMetni.' | '.$kurDetay) : $kurDetay;

        return DB::transaction(function () use ($firmaId, $kaynakTipi, $kaynakHesapId, $hedefTipi, $hedefHesapId, $kaynakTutar, $hedefTutar, $negatifKaynak, $kaynakParaBirimi, $hedefParaBirimi, $tarih, $finansAciklama): array {
            $finans = $this->finansKaydiOlustur([
                'firma_id' => $firmaId,
                'tur' => FinansHareketTuru::Virman,
                'tarih' => $tarih,
                'vade_tarihi' => null,
                'tutar' => $kaynakTutar,
                'para_birimi' => $kaynakParaBirimi,
                'cari_id' => null,
                'aciklama' => $finansAciklama,
                'referans_turu' => null,
                'referans_id' => null,
                'durum' => FinansHareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);

            $kaynak = $this->virmanHareketiOlustur(
                $firmaId,
                (int) $finans->getKey(),
                $kaynakTipi,
                $kaynakHesapId,
                $negatifKaynak,
                $kaynakParaBirimi
            );

            $hedef = $this->virmanHareketiOlustur(
                $firmaId,
                (int) $finans->getKey(),
                $hedefTipi,
                $hedefHesapId,
                $hedefTutar,
                $hedefParaBirimi
            );

            return ['finans' => $finans, 'kaynak' => $kaynak, 'hedef' => $hedef];
        });
    }

    public function virmanKasaBankaKaydet(
        int $firmaId,
        int $kasaHesapId,
        int $bankaHesapId,
        string $tutar,
        string $paraBirimi,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
    ): array {
        $sonuc = $this->virmanHesaplarArasiKaydet(
            $firmaId,
            'kasa',
            $kasaHesapId,
            'banka',
            $bankaHesapId,
            $tutar,
            $paraBirimi,
            $tarih,
            $aciklama
        );

        return [
            'finans' => $sonuc['finans'],
            'kasa' => $sonuc['kaynak'],
            'banka' => $sonuc['hedef'],
        ];
    }

    /**
     * Tahsilat (cari PB) -> hesap (hesap PB) kur ile kayit.
     *
     * @return array{finans: FinansHareketi, hesap: KasaHareketi|BankaHareketi|PosHareketi, cari: CariHareketi}
     */
    public function tahsilatKurIleKaydet(
        int $firmaId,
        int $cariId,
        string $hesapTipi,
        int $hesapId,
        string $cariTutari,
        string $cariParaBirimi,
        string $hesapTutari,
        string $hesapParaBirimi,
        string $kur,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
        ?string $referansTuru = null,
        ?int $referansId = null,
    ): array {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);

        $cariParaBirimi = strtoupper(trim($cariParaBirimi));
        $hesapParaBirimi = strtoupper(trim($hesapParaBirimi));
        $this->paraBirimiDogrula($cariParaBirimi);
        $this->paraBirimiDogrula($hesapParaBirimi);
        if ($cariParaBirimi === $hesapParaBirimi) {
            throw new IsKuraliIstisnasi('Kurli tahsilat icin para birimleri farkli olmalidir.');
        }

        $kur = number_format((float) $kur, 8, '.', '');
        $cariTutari = number_format((float) $cariTutari, 2, '.', '');
        $hesapTutari = number_format((float) $hesapTutari, 2, '.', '');
        if ((float) $kur <= 0 || bccomp($cariTutari, '0', 2) <= 0 || bccomp($hesapTutari, '0', 2) <= 0) {
            throw new IsKuraliIstisnasi('Kur ve tutarlar sifirdan buyuk olmalidir.');
        }

        $hesapTipi = strtolower(trim($hesapTipi));
        if (! in_array($hesapTipi, ['kasa', 'banka', 'pos'], true)) {
            throw new IsKuraliIstisnasi('Gecersiz hesap tipi.');
        }

        $cari = $this->cariyiYukleVeDogrula($firmaId, $cariId, $cariParaBirimi);
        $hesap = $this->virmanHesabiYukleVeDogrula($firmaId, $hesapTipi, $hesapId, $hesapParaBirimi);
        $kurNotu = 'Kur: '.$kur.' ('.$cariParaBirimi.' -> '.$hesapParaBirimi.')';
        $aciklamaMetni = trim((string) $aciklama);
        $ekAciklama = $aciklamaMetni !== '' ? ($aciklamaMetni.' | '.$kurNotu) : $kurNotu;

        return DB::transaction(function () use ($firmaId, $cari, $hesapTipi, $hesap, $cariTutari, $cariParaBirimi, $hesapTutari, $hesapParaBirimi, $kur, $tarih, $aciklama, $ekAciklama, $referansTuru, $referansId): array {
            $finansAlanlari = [
                'firma_id' => $firmaId,
                'tur' => FinansHareketTuru::Tahsilat,
                'tarih' => $tarih,
                'vade_tarihi' => null,
                'tutar' => $cariTutari,
                'para_birimi' => $cariParaBirimi,
                'cari_id' => $cari->getKey(),
                'aciklama' => $aciklama,
                'ek_aciklama' => $ekAciklama,
                'referans_turu' => $referansTuru,
                'referans_id' => $referansId,
                'durum' => FinansHareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ];
            if ($hesapParaBirimi === strtoupper((string) config('muhasebe.coklu_para_birimi.baz_para_birimi', 'TRY'))) {
                $finansAlanlari['baz_tutar'] = $hesapTutari;
                $finansAlanlari['baz_para_birimi'] = $hesapParaBirimi;
                $finansAlanlari['kur'] = bcdiv($hesapTutari, $cariTutari, 8);
            }
            $finans = $this->finansKaydiOlustur($finansAlanlari);

            $hesapHareket = $this->virmanHareketiOlustur(
                $firmaId,
                (int) $finans->getKey(),
                $hesapTipi,
                (int) $hesap->getKey(),
                $hesapTutari,
                $hesapParaBirimi
            );

            $cariAlanlari = [
                'cari_id' => (int) $cari->getKey(),
                'belge_turu' => CariHareketBelgeTuru::Tahsilat,
                'belge_id' => (int) $finans->getKey(),
                'islem_tarihi' => $tarih,
                'borc' => $cariTutari,
                'alacak' => '0',
                'para_birimi' => $cariParaBirimi,
                'aciklama' => $aciklama,
            ];
            if ($hesapParaBirimi === strtoupper((string) config('muhasebe.coklu_para_birimi.baz_para_birimi', 'TRY'))) {
                $cariAlanlari += [
                    'baz_borc' => $hesapTutari,
                    'baz_alacak' => '0',
                    'baz_para_birimi' => $hesapParaBirimi,
                    'kur' => $kur,
                ];
            }
            $cariHareket = $this->cariHareketServisi->kayitOlustur($firmaId, $cariAlanlari);

            $this->faturaKapamaVeOtomatikDagit($finans);

            return ['finans' => $finans, 'hesap' => $hesapHareket, 'cari' => $cariHareket];
        });
    }

    /**
     * Odeme (cari PB) <- hesap (hesap PB) kur ile kayit.
     *
     * @return array{finans: FinansHareketi, hesap: KasaHareketi|BankaHareketi, cari: CariHareketi}
     */
    public function odemeKurIleKaydet(
        int $firmaId,
        int $cariId,
        string $hesapTipi,
        int $hesapId,
        string $cariTutari,
        string $cariParaBirimi,
        string $hesapTutari,
        string $hesapParaBirimi,
        string $kur,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
        ?string $referansTuru = null,
        ?int $referansId = null,
    ): array {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);

        $cariParaBirimi = strtoupper(trim($cariParaBirimi));
        $hesapParaBirimi = strtoupper(trim($hesapParaBirimi));
        $this->paraBirimiDogrula($cariParaBirimi);
        $this->paraBirimiDogrula($hesapParaBirimi);
        if ($cariParaBirimi === $hesapParaBirimi) {
            throw new IsKuraliIstisnasi('Kurli odeme icin para birimleri farkli olmalidir.');
        }

        $hesapTipi = strtolower(trim($hesapTipi));
        if (! in_array($hesapTipi, ['kasa', 'banka'], true)) {
            throw new IsKuraliIstisnasi('Kurli odeme yalnizca kasa veya banka ile yapilabilir.');
        }

        $kur = number_format((float) $kur, 8, '.', '');
        $cariTutari = number_format((float) $cariTutari, 2, '.', '');
        $hesapTutari = number_format((float) $hesapTutari, 2, '.', '');
        if ((float) $kur <= 0 || bccomp($cariTutari, '0', 2) <= 0 || bccomp($hesapTutari, '0', 2) <= 0) {
            throw new IsKuraliIstisnasi('Kur ve tutarlar sifirdan buyuk olmalidir.');
        }

        $cari = $this->cariyiYukleVeDogrula($firmaId, $cariId, $cariParaBirimi);
        $hesap = $this->virmanHesabiYukleVeDogrula($firmaId, $hesapTipi, $hesapId, $hesapParaBirimi);
        $kurNotu = 'Kur: '.$kur.' ('.$cariParaBirimi.' -> '.$hesapParaBirimi.')';
        $aciklamaMetni = trim((string) $aciklama);
        $ekAciklama = $aciklamaMetni !== '' ? ($aciklamaMetni.' | '.$kurNotu) : $kurNotu;
        $negatifHesap = bcmul($hesapTutari, '-1', 2);

        return DB::transaction(function () use ($firmaId, $cari, $hesapTipi, $hesap, $cariTutari, $cariParaBirimi, $hesapTutari, $hesapParaBirimi, $negatifHesap, $kur, $tarih, $aciklama, $ekAciklama, $referansTuru, $referansId): array {
            $finansAlanlari = [
                'firma_id' => $firmaId,
                'tur' => FinansHareketTuru::Odeme,
                'tarih' => $tarih,
                'vade_tarihi' => null,
                'tutar' => $cariTutari,
                'para_birimi' => $cariParaBirimi,
                'cari_id' => $cari->getKey(),
                'aciklama' => $aciklama,
                'ek_aciklama' => $ekAciklama,
                'referans_turu' => $referansTuru,
                'referans_id' => $referansId,
                'durum' => FinansHareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ];
            if ($hesapParaBirimi === strtoupper((string) config('muhasebe.coklu_para_birimi.baz_para_birimi', 'TRY'))) {
                $finansAlanlari['baz_tutar'] = $hesapTutari;
                $finansAlanlari['baz_para_birimi'] = $hesapParaBirimi;
                $finansAlanlari['kur'] = bcdiv($hesapTutari, $cariTutari, 8);
            }
            $finans = $this->finansKaydiOlustur($finansAlanlari);

            $hesapHareket = $this->virmanHareketiOlustur(
                $firmaId,
                (int) $finans->getKey(),
                $hesapTipi,
                (int) $hesap->getKey(),
                $negatifHesap,
                $hesapParaBirimi
            );

            $cariAlanlari = [
                'cari_id' => (int) $cari->getKey(),
                'belge_turu' => CariHareketBelgeTuru::Odeme,
                'belge_id' => (int) $finans->getKey(),
                'islem_tarihi' => $tarih,
                'borc' => '0',
                'alacak' => $cariTutari,
                'para_birimi' => $cariParaBirimi,
                'aciklama' => $aciklama,
            ];
            if ($hesapParaBirimi === strtoupper((string) config('muhasebe.coklu_para_birimi.baz_para_birimi', 'TRY'))) {
                $cariAlanlari += [
                    'baz_borc' => '0',
                    'baz_alacak' => $hesapTutari,
                    'baz_para_birimi' => $hesapParaBirimi,
                    'kur' => $kur,
                ];
            }
            $cariHareket = $this->cariHareketServisi->kayitOlustur($firmaId, $cariAlanlari);

            $this->faturaKapamaVeOtomatikDagit($finans);

            return ['finans' => $finans, 'hesap' => $hesapHareket, 'cari' => $cariHareket];
        });
    }

    /**
     * Banka → kasa: banka negatif, kasa pozitif.
     *
     * @return array{finans: FinansHareketi, kasa: KasaHareketi, banka: BankaHareketi}
     */
    public function virmanBankaKasayaKaydet(
        int $firmaId,
        int $bankaHesapId,
        int $kasaHesapId,
        string $tutar,
        string $paraBirimi,
        \DateTimeInterface|string $tarih,
        ?string $aciklama = null,
    ): array {
        $sonuc = $this->virmanHesaplarArasiKaydet(
            $firmaId,
            'banka',
            $bankaHesapId,
            'kasa',
            $kasaHesapId,
            $tutar,
            $paraBirimi,
            $tarih,
            $aciklama
        );

        return [
            'finans' => $sonuc['finans'],
            'kasa' => $sonuc['hedef'],
            'banka' => $sonuc['kaynak'],
        ];
    }

    public function tersKayitOlustur(FinansHareketi $finans, ?string $aciklama = null): FinansHareketi
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt((int) $finans->firma_id);

        $mevcutTers = FinansHareketi::query()
            ->where('iptal_edilen_hareket_id', $finans->getKey())
            ->where('durum', FinansHareketDurumu::Aktif)
            ->orderByDesc('id')
            ->first();
        if ($mevcutTers instanceof FinansHareketi) {
            return $mevcutTers;
        }

        if ($finans->durum !== FinansHareketDurumu::Aktif) {
            throw new IsKuraliIstisnasi('Yalnızca aktif finans hareketi terslenebilir.');
        }

        try {
            return DB::transaction(function () use ($finans, $aciklama): FinansHareketi {
                // Tersleme aynı finans hareketi için eşzamanlı çağrılarda tekilleştirilmelidir.
                $finans = FinansHareketi::query()
                    ->lockForUpdate()
                    ->whereKey($finans->getKey())
                    ->firstOrFail();

                $mevcutTers = FinansHareketi::query()
                    ->where('iptal_edilen_hareket_id', $finans->getKey())
                    ->where('durum', FinansHareketDurumu::Aktif)
                    ->orderByDesc('id')
                    ->first();
                if ($mevcutTers instanceof FinansHareketi) {
                    return $mevcutTers;
                }

                app(AlacakTahsilatServisi::class)->finansTahsilatiTersleninceDagitimiGeriAl($finans);

                $cariSatirlari = CariHareketi::query()
                    ->where('firma_id', $finans->firma_id)
                    ->whereIn('belge_turu', [CariHareketBelgeTuru::Tahsilat, CariHareketBelgeTuru::Odeme])
                    ->where('belge_id', $finans->getKey())
                    ->where('durum', CariHareketDurumu::Aktif)
                    ->get();

                foreach ($cariSatirlari as $satir) {
                    // Cari bakiyeleri yalnız aktif satırları toplar. Finans tersinde
                    // ayrıca aktif ters cari satırı üretmek bakiyeyi iki kez bozar.
                    app(CariHareketFifoEslestirmeServisi::class)
                        ->iptalEdilenHareketEslesmeleriniSil($satir);
                    $satir->update(['durum' => CariHareketDurumu::Iptal]);
                }

                $yeniFinans = $this->finansKaydiOlustur([
                    'firma_id' => $finans->firma_id,
                    'tur' => FinansHareketTuru::Mahsup,
                    'tarih' => now(),
                    'vade_tarihi' => $finans->vade_tarihi,
                    'tutar' => $finans->tutar,
                    'para_birimi' => $finans->para_birimi,
                    'baz_tutar' => $finans->baz_tutar,
                    'baz_para_birimi' => $finans->baz_para_birimi,
                    'kur' => $finans->kur,
                    'cari_id' => null,
                    'aciklama' => $aciklama ?? ('Finans ters kayıt: #'.$finans->getKey()),
                    'referans_turu' => 'finans_hareketi',
                    'referans_id' => $finans->getKey(),
                    'durum' => FinansHareketDurumu::Aktif,
                    'iptal_edilen_hareket_id' => $finans->getKey(),
                ]);

                foreach ($finans->kasaHareketleri()->where('durum', HareketDurumu::Aktif)->get() as $k) {
                    $k->update(['durum' => HareketDurumu::Iptal]);
                    KasaHareketi::query()->create([
                        'firma_id' => $k->firma_id,
                        'finans_hareket_id' => $yeniFinans->getKey(),
                        'kasa_hesap_id' => $k->kasa_hesap_id,
                        'tutar' => bcmul((string) $k->tutar, '-1', 2),
                        'para_birimi' => $k->para_birimi,
                        'durum' => HareketDurumu::Iptal,
                        'iptal_edilen_hareket_id' => $k->getKey(),
                    ]);
                }

                foreach ($finans->bankaHareketleri()->where('durum', HareketDurumu::Aktif)->get() as $b) {
                    $b->update(['durum' => HareketDurumu::Iptal]);
                    BankaHareketi::query()->create([
                        'firma_id' => $b->firma_id,
                        'finans_hareket_id' => $yeniFinans->getKey(),
                        'banka_hesap_id' => $b->banka_hesap_id,
                        'tutar' => bcmul((string) $b->tutar, '-1', 2),
                        'para_birimi' => $b->para_birimi,
                        'durum' => HareketDurumu::Iptal,
                        'iptal_edilen_hareket_id' => $b->getKey(),
                    ]);
                }

                foreach ($finans->posHareketleri()->where('durum', HareketDurumu::Aktif)->get() as $p) {
                    $p->update(['durum' => HareketDurumu::Iptal]);
                    PosHareketi::query()->create([
                        'firma_id' => $p->firma_id,
                        'finans_hareket_id' => $yeniFinans->getKey(),
                        'pos_hesap_id' => $p->pos_hesap_id,
                        'tutar' => bcmul((string) $p->tutar, '-1', 2),
                        'para_birimi' => $p->para_birimi,
                        'durum' => HareketDurumu::Iptal,
                        'iptal_edilen_hareket_id' => $p->getKey(),
                    ]);
                }

                $finans->update(['durum' => FinansHareketDurumu::Iptal]);
                $this->faturaFinansKapamaServisi->finansTersleninceFaturaDurumunuYenile($finans);

                return $yeniFinans;
            });
        } catch (Throwable $e) {
            $this->sistemOlayServisi->olayKaydet('finans.ters_kayit_hatasi', 'error', 'Finans ters kayit olusturulamadi.', [
                'firma_id' => (int) $finans->firma_id,
                'finans_hareket_id' => (int) $finans->id,
            ]);
            throw $e;
        }
    }

    /**
     * E-ticaret sipariş iptali gibi oturum/kimlik bağımlı olmayan arka plan akışlarında
     * finans ters kaydı oluşturur.
     *
     * Not: Bu metot, {@see tersKayitOlustur()} içindeki "kimlik doğrulanmış kullanıcı gerekir"
     * kontrolünü bypass etmemek için değil; e-ticaret bağlamındaki izin kontrolünü kullanmak için ayrılmıştır.
     */
    public function tersKayitOlusturEcommerce(FinansHareketi $finans, ?string $aciklama = null): FinansHareketi
    {
        $this->firmaDenetleyicisi->eTicaretYazmaIcinFirmaKontrolEt((int) $finans->firma_id);

        $mevcutTers = FinansHareketi::query()
            ->withoutGlobalScopes()
            ->where('iptal_edilen_hareket_id', $finans->getKey())
            ->where('durum', FinansHareketDurumu::Aktif)
            ->orderByDesc('id')
            ->first();
        if ($mevcutTers instanceof FinansHareketi) {
            return $mevcutTers;
        }

        if ($finans->durum !== FinansHareketDurumu::Aktif) {
            throw new IsKuraliIstisnasi('Yalnızca aktif finans hareketi terslenebilir.');
        }

        try {
            return DB::transaction(function () use ($finans, $aciklama): FinansHareketi {
                $finans = FinansHareketi::query()
                    ->withoutGlobalScopes()
                    ->lockForUpdate()
                    ->whereKey($finans->getKey())
                    ->firstOrFail();

                $mevcutTers = FinansHareketi::query()
                    ->withoutGlobalScopes()
                    ->where('iptal_edilen_hareket_id', $finans->getKey())
                    ->where('durum', FinansHareketDurumu::Aktif)
                    ->orderByDesc('id')
                    ->first();
                if ($mevcutTers instanceof FinansHareketi) {
                    return $mevcutTers;
                }

                app(AlacakTahsilatServisi::class)->finansTahsilatiTersleninceDagitimiGeriAl($finans);

                $cariSatirlari = CariHareketi::query()
                    ->where('firma_id', $finans->firma_id)
                    ->whereIn('belge_turu', [CariHareketBelgeTuru::Tahsilat, CariHareketBelgeTuru::Odeme])
                    ->where('belge_id', $finans->getKey())
                    ->where('durum', CariHareketDurumu::Aktif)
                    ->get();

                foreach ($cariSatirlari as $satir) {
                    app(CariHareketFifoEslestirmeServisi::class)
                        ->iptalEdilenHareketEslesmeleriniSil($satir);
                    $satir->update(['durum' => CariHareketDurumu::Iptal]);
                }

                $yeniFinans = $this->finansKaydiOlustur([
                    'firma_id' => $finans->firma_id,
                    'tur' => FinansHareketTuru::Mahsup,
                    'tarih' => now(),
                    'vade_tarihi' => $finans->vade_tarihi,
                    'tutar' => $finans->tutar,
                    'para_birimi' => $finans->para_birimi,
                    'baz_tutar' => $finans->baz_tutar,
                    'baz_para_birimi' => $finans->baz_para_birimi,
                    'kur' => $finans->kur,
                    'cari_id' => null,
                    'aciklama' => $aciklama ?? ('Finans ters kayıt: #'.$finans->getKey()),
                    'referans_turu' => 'finans_hareketi',
                    'referans_id' => $finans->getKey(),
                    'durum' => FinansHareketDurumu::Aktif,
                    'iptal_edilen_hareket_id' => $finans->getKey(),
                ]);

                foreach ($finans->kasaHareketleri()->where('durum', HareketDurumu::Aktif)->get() as $k) {
                    $k->update(['durum' => HareketDurumu::Iptal]);
                    KasaHareketi::query()->create([
                        'firma_id' => $k->firma_id,
                        'finans_hareket_id' => $yeniFinans->getKey(),
                        'kasa_hesap_id' => $k->kasa_hesap_id,
                        'tutar' => bcmul((string) $k->tutar, '-1', 2),
                        'para_birimi' => $k->para_birimi,
                        'durum' => HareketDurumu::Iptal,
                        'iptal_edilen_hareket_id' => $k->getKey(),
                    ]);
                }

                foreach ($finans->bankaHareketleri()->where('durum', HareketDurumu::Aktif)->get() as $b) {
                    $b->update(['durum' => HareketDurumu::Iptal]);
                    BankaHareketi::query()->create([
                        'firma_id' => $b->firma_id,
                        'finans_hareket_id' => $yeniFinans->getKey(),
                        'banka_hesap_id' => $b->banka_hesap_id,
                        'tutar' => bcmul((string) $b->tutar, '-1', 2),
                        'para_birimi' => $b->para_birimi,
                        'durum' => HareketDurumu::Iptal,
                        'iptal_edilen_hareket_id' => $b->getKey(),
                    ]);
                }

                foreach ($finans->posHareketleri()->where('durum', HareketDurumu::Aktif)->get() as $p) {
                    $p->update(['durum' => HareketDurumu::Iptal]);
                    PosHareketi::query()->create([
                        'firma_id' => $p->firma_id,
                        'finans_hareket_id' => $yeniFinans->getKey(),
                        'pos_hesap_id' => $p->pos_hesap_id,
                        'tutar' => bcmul((string) $p->tutar, '-1', 2),
                        'para_birimi' => $p->para_birimi,
                        'durum' => HareketDurumu::Iptal,
                        'iptal_edilen_hareket_id' => $p->getKey(),
                    ]);
                }

                $finans->update(['durum' => FinansHareketDurumu::Iptal]);
                $this->faturaFinansKapamaServisi->finansTersleninceFaturaDurumunuYenile($finans);

                return $yeniFinans;
            });
        } catch (Throwable $e) {
            $this->sistemOlayServisi->olayKaydet('finans.ters_kayit_hatasi', 'error', 'E-ticaret finans ters kaydi olusturulamadi.', [
                'firma_id' => (int) $finans->firma_id,
                'finans_hareket_id' => (int) $finans->id,
            ]);
            throw $e;
        }
    }

    private function virmanHesabiYukleVeDogrula(
        int $firmaId,
        string $hesapTipi,
        int $hesapId,
        string $paraBirimi,
    ): KasaHesabi|BankaHesabi|PosHesabi {
        return match ($hesapTipi) {
            'kasa' => $this->kasayiYukleVeDogrula($firmaId, $hesapId, $paraBirimi),
            'banka' => $this->bankayiYukleVeDogrula($firmaId, $hesapId, $paraBirimi),
            'pos' => $this->posuYukleVeDogrula($firmaId, $hesapId, $paraBirimi),
            default => throw new IsKuraliIstisnasi('Geçersiz hesap tipi.'),
        };
    }

    private function virmanHareketiOlustur(
        int $firmaId,
        int $finansHareketId,
        string $hesapTipi,
        int $hesapId,
        string $tutar,
        string $paraBirimi,
    ): KasaHareketi|BankaHareketi|PosHareketi {
        return match ($hesapTipi) {
            'kasa' => KasaHareketi::query()->create([
                'firma_id' => $firmaId,
                'finans_hareket_id' => $finansHareketId,
                'kasa_hesap_id' => $hesapId,
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'durum' => HareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]),
            'banka' => BankaHareketi::query()->create([
                'firma_id' => $firmaId,
                'finans_hareket_id' => $finansHareketId,
                'banka_hesap_id' => $hesapId,
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'durum' => HareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]),
            'pos' => PosHareketi::query()->create([
                'firma_id' => $firmaId,
                'finans_hareket_id' => $finansHareketId,
                'pos_hesap_id' => $hesapId,
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'durum' => HareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]),
            default => throw new IsKuraliIstisnasi('Geçersiz hesap tipi.'),
        };
    }

    private function cariyiYukleVeDogrula(int $firmaId, int $cariId, string $paraBirimi, bool $eticaretKapsaminda = false): Cari
    {
        $sorgu = Cari::query();
        if ($eticaretKapsaminda) {
            $sorgu = $sorgu->withoutGlobalScopes();
        }

        $cari = $sorgu->whereKey($cariId)->firstOrFail();
        if ((int) $cari->firma_id !== $firmaId) {
            throw new IsKuraliIstisnasi('Cari firmaya ait değil.');
        }
        return $cari;
    }

    private function kasayiYukleVeDogrula(int $firmaId, int $kasaHesapId, string $paraBirimi): KasaHesabi
    {
        $kasa = KasaHesabi::query()->whereKey($kasaHesapId)->firstOrFail();
        if ((int) $kasa->firma_id !== $firmaId) {
            throw new IsKuraliIstisnasi('Kasa hesabı firmaya ait değil.');
        }
        if (strtoupper((string) $kasa->para_birimi) !== strtoupper($paraBirimi)) {
            throw new IsKuraliIstisnasi('Kasa para birimi uyuşmuyor.');
        }

        return $kasa;
    }

    private function bankayiYukleVeDogrula(int $firmaId, int $bankaHesapId, string $paraBirimi): BankaHesabi
    {
        $banka = BankaHesabi::query()->whereKey($bankaHesapId)->firstOrFail();
        if ((int) $banka->firma_id !== $firmaId) {
            throw new IsKuraliIstisnasi('Banka hesabı firmaya ait değil.');
        }
        if (strtoupper((string) $banka->para_birimi) !== strtoupper($paraBirimi)) {
            throw new IsKuraliIstisnasi('Banka para birimi uyuşmuyor.');
        }

        return $banka;
    }

    /**
     * POS yükler; global tenant scope (HTTP) veya konsol bypass sonrası da firma_id burada zorunlu doğrulanır.
     */
    private function posuYukleVeDogrula(int $firmaId, int $posHesapId, string $paraBirimi): PosHesabi
    {
        $pos = PosHesabi::query()->whereKey($posHesapId)->firstOrFail();
        if ((int) $pos->firma_id !== $firmaId) {
            throw new IsKuraliIstisnasi('POS hesabı firmaya ait değil.');
        }
        if (strtoupper((string) $pos->para_birimi) !== strtoupper($paraBirimi)) {
            throw new IsKuraliIstisnasi('POS para birimi uyuşmuyor.');
        }

        return $pos;
    }

    private function paraBirimiDogrula(string $kod): void
    {
        if (strlen($kod) !== 3) {
            throw new IsKuraliIstisnasi('Para birimi ISO 4217 (3 karakter) olmalıdır.');
        }
    }

    /**
     * Banka/POS satırlarında ve finans `ek_aciklama` alanında kullanılacak birleşik metin.
     */
    private function bankaPosEkMetniOlustur(?string $anaMetin, ?string $birinciKod = null, ?string $ikinciKod = null, ?string $detay = null): ?string
    {
        $parcalar = [];
        if ($anaMetin !== null && $anaMetin !== '') {
            $parcalar[] = $anaMetin;
        }
        if ($birinciKod !== null && $birinciKod !== '') {
            $parcalar[] = 'Dekont / slip no: '.$birinciKod;
        }
        if ($ikinciKod !== null && $ikinciKod !== '') {
            $parcalar[] = 'İşlem / provizyon ref.: '.$ikinciKod;
        }
        if ($detay !== null && $detay !== '') {
            $parcalar[] = $detay;
        }

        return $parcalar === [] ? null : implode(' | ', $parcalar);
    }
}
