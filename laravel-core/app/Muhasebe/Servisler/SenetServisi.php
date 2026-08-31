<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\Muhasebe\Senet;
use App\Models\Muhasebe\SenetHareketi;
use App\Muhasebe\Enumlar\SenetDurumu;
use App\Muhasebe\Enumlar\SenetHareketDurumu;
use App\Muhasebe\Enumlar\SenetIslemTuru;
use App\Muhasebe\Enumlar\SenetTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Guvenlik\MuhasebeFirmaErisimDenetleyicisi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SenetServisi
{
    public function __construct(
        private readonly MuhasebeFirmaErisimDenetleyicisi $firmaDenetleyicisi,
        private readonly FinansHareketServisi $finansHareketServisi,
    ) {}

    /**
     * Müşteriden alınan senedi portföye alır. Para tahsilatı oluşturmaz.
     *
     * @param array<string,mixed> $veri
     */
    public function girisKaydet(int $firmaId, array $veri): Senet
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);

        $cariId = (int) ($veri['cari_id'] ?? 0);
        $paraBirimi = strtoupper(trim((string) ($veri['para_birimi'] ?? 'TRY')));
        $tutar = $this->tutarHazirla($veri['tutar'] ?? null);
        $senetNo = trim((string) ($veri['senet_no'] ?? ''));
        $islemTarihi = $this->tarihZorunlu($veri['islem_tarihi'] ?? null);
        $onGorselYolu = $this->gorselYolunuDogrula($firmaId, $veri['on_gorsel_yolu'] ?? null);
        $arkaGorselYolu = $this->gorselYolunuDogrula($firmaId, $veri['arka_gorsel_yolu'] ?? null);
        $idempotencyKey = $this->idempotencyKey('giris', $firmaId, [
            'cari_id' => $cariId,
            'senet_no' => $senetNo,
            'tutar' => $tutar,
            'para_birimi' => $paraBirimi,
            'islem_tarihi' => $islemTarihi,
            'vade_tarihi' => $veri['vade_tarihi'] ?? null,
        ]);

        if ($senetNo === '') {
            throw new IsKuraliIstisnasi('Senet numarası zorunludur.');
        }

        $this->cariyiDogrula($firmaId, $cariId, $paraBirimi);

        return DB::transaction(function () use ($firmaId, $veri, $cariId, $paraBirimi, $tutar, $senetNo, $islemTarihi, $idempotencyKey, $onGorselYolu, $arkaGorselYolu): Senet {
            $mevcut = SenetHareketi::query()
                ->where('firma_id', $firmaId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($mevcut) {
                return $mevcut->senet()->firstOrFail();
            }

            $kullaniciId = Auth::id();
            $senet = new Senet([
                'firma_id' => $firmaId,
                'turu' => SenetTuru::Alinan,
                'durum' => SenetDurumu::Portfoyde,
                'senet_no' => $senetNo,
                'duzenleme_yeri' => $this->bosVeyaMetin($veri['duzenleme_yeri'] ?? null),
                'odeme_yeri' => $this->bosVeyaMetin($veri['odeme_yeri'] ?? null),
                'avalist_adi' => $this->bosVeyaMetin($veri['avalist_adi'] ?? null),
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'duzenleme_tarihi' => $veri['duzenleme_tarihi'] ?? null,
                'vade_tarihi' => $veri['vade_tarihi'] ?? null,
                'sorumlu_kullanici_id' => $kullaniciId,
                'olusturan_kullanici_id' => $kullaniciId,
                'on_gorsel_yolu' => $onGorselYolu,
                'arka_gorsel_yolu' => $arkaGorselYolu,
                'aciklama' => $this->bosVeyaMetin($veri['aciklama'] ?? null),
            ]);
            $senet->setAttribute('para_birimi_snapshot_tarihi', $islemTarihi);
            $senet->save();

            SenetHareketi::query()->create([
                'firma_id' => $firmaId,
                'senet_id' => $senet->getKey(),
                'islem_turu' => SenetIslemTuru::Giris,
                'cari_id' => $cariId,
                'islem_yapan_kullanici_id' => $kullaniciId,
                'islem_tarihi' => $islemTarihi,
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'idempotency_key' => $idempotencyKey,
                'durum' => SenetHareketDurumu::Aktif,
                'aciklama' => $this->bosVeyaMetin($veri['aciklama'] ?? null),
            ]);

            return $senet->fresh();
        });
    }

    /**
     * Firmanın kendi senedini veya portföydeki senedi cariye verir. Para ödemez.
     *
     * @param array<string,mixed> $veri
     */
    public function cikisKaydet(int $firmaId, array $veri): Senet
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);

        $kaynak = (string) ($veri['kaynak'] ?? 'kendi');
        if (! in_array($kaynak, ['kendi', 'portfoy'], true)) {
            throw new IsKuraliIstisnasi('Geçersiz senet çıkış kaynağı.');
        }

        $cariId = (int) ($veri['cari_id'] ?? 0);
        $islemTarihi = $this->tarihZorunlu($veri['islem_tarihi'] ?? null);
        $senetId = (int) ($veri['senet_id'] ?? 0);
        $senetNo = trim((string) ($veri['senet_no'] ?? ''));
        $idempotencyKey = $this->idempotencyKey('cikis', $firmaId, [
            'kaynak' => $kaynak,
            'senet_id' => $senetId,
            'cari_id' => $cariId,
            'senet_no' => $senetNo,
            'tutar' => $kaynak === 'kendi' ? $this->tutarHazirla($veri['tutar'] ?? null) : null,
            'para_birimi' => strtoupper(trim((string) ($veri['para_birimi'] ?? 'TRY'))),
            'islem_tarihi' => $islemTarihi,
        ]);

        return DB::transaction(function () use ($firmaId, $veri, $kaynak, $cariId, $islemTarihi, $senetId, $senetNo, $idempotencyKey): Senet {
            $mevcut = SenetHareketi::query()
                ->where('firma_id', $firmaId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($mevcut) {
                return $mevcut->senet()->firstOrFail();
            }

            if ($kaynak === 'portfoy') {
                $senet = Senet::query()
                    ->where('firma_id', $firmaId)
                    ->whereKey($senetId)
                    ->where('turu', SenetTuru::Alinan->value)
                    ->where('durum', SenetDurumu::Portfoyde->value)
                    ->lockForUpdate()
                    ->firstOrFail();
                $giris = $senet->girisHareketi()->where('durum', SenetHareketDurumu::Aktif->value)->first();
                if (! $giris) {
                    throw new IsKuraliIstisnasi('Portföy senedi aktif giriş hareketine sahip değil.');
                }
                $tutar = (string) $senet->tutar;
                    $paraBirimi = strtoupper((string) $senet->para_birimi);
            } else {
                $tutar = $this->tutarHazirla($veri['tutar'] ?? null);
                $paraBirimi = strtoupper(trim((string) ($veri['para_birimi'] ?? 'TRY')));
                if ($senetNo === '') {
                    throw new IsKuraliIstisnasi('Senet numarası zorunludur.');
                }
                $senet = new Senet([
                    'firma_id' => $firmaId,
                    'turu' => SenetTuru::Verilen,
                    'durum' => SenetDurumu::Verildi,
                    'senet_no' => $senetNo,
                    'duzenleme_yeri' => $this->bosVeyaMetin($veri['duzenleme_yeri'] ?? null),
                    'odeme_yeri' => $this->bosVeyaMetin($veri['odeme_yeri'] ?? null),
                    'avalist_adi' => $this->bosVeyaMetin($veri['avalist_adi'] ?? null),
                    'tutar' => $tutar,
                    'para_birimi' => $paraBirimi,
                    'duzenleme_tarihi' => $veri['duzenleme_tarihi'] ?? null,
                    'vade_tarihi' => $veri['vade_tarihi'] ?? null,
                    'sorumlu_kullanici_id' => Auth::id(),
                    'olusturan_kullanici_id' => Auth::id(),
                    'on_gorsel_yolu' => $this->gorselYolunuDogrula($firmaId, $veri['on_gorsel_yolu'] ?? null),
                    'arka_gorsel_yolu' => $this->gorselYolunuDogrula($firmaId, $veri['arka_gorsel_yolu'] ?? null),
                    'aciklama' => $this->bosVeyaMetin($veri['aciklama'] ?? null),
                ]);
                $senet->setAttribute('para_birimi_snapshot_tarihi', $islemTarihi);
                $senet->save();
            }

            $aktifCikis = SenetHareketi::query()
                ->where('firma_id', $firmaId)
                ->where('senet_id', $senet->getKey())
                ->where('islem_turu', SenetIslemTuru::Cikis->value)
                ->where('durum', SenetHareketDurumu::Aktif->value)
                ->lockForUpdate()
                ->exists();
            if ($aktifCikis) {
                throw new IsKuraliIstisnasi('Bu senet için zaten aktif bir çıkış hareketi bulunuyor.');
            }

            $this->cariyiDogrula($firmaId, $cariId, $paraBirimi);

            SenetHareketi::query()->create([
                'firma_id' => $firmaId,
                'senet_id' => $senet->getKey(),
                'islem_turu' => SenetIslemTuru::Cikis,
                'cari_id' => $cariId,
                'islem_yapan_kullanici_id' => Auth::id(),
                'islem_tarihi' => $islemTarihi,
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'idempotency_key' => $idempotencyKey,
                'durum' => SenetHareketDurumu::Aktif,
                'aciklama' => $this->bosVeyaMetin($veri['aciklama'] ?? null),
            ]);

            $senet->update(['durum' => SenetDurumu::Verildi]);

            return $senet->fresh();
        });
    }

    /**
     * Alınan senedin gerçek ödeme tahsilatını oluşturur ve senedi kapatır.
     *
     * @param array<string,mixed> $veri
     */
    public function tahsilatEkle(Senet $senet, array $veri): Senet
    {
        return $this->odemeKaydet($senet, $veri, 'tahsilat');
    }

    /**
     * Geriye dönük çağrılar için korunur; yeni ekranlar tahsilatEkle kullanmalıdır.
     *
     * @param array<string,mixed> $veri
     */
    public function odemeAl(Senet $senet, array $veri): Senet
    {
        return $this->tahsilatEkle($senet, $veri);
    }

    /**
     * Firmanın kendi senedinin ödeme hareketini oluşturur ve senedi kapatır.
     *
     * @param array<string,mixed> $veri
     */
    public function odemeYap(Senet $senet, array $veri): Senet
    {
        return $this->odemeKaydet($senet, $veri, 'odeme');
    }

    /**
     * @param array<string,mixed> $veri
     */
    private function odemeKaydet(Senet $senet, array $veri, string $hareketTuru): Senet
    {
        $firmaId = (int) $senet->firma_id;
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);

        $kanal = strtolower(trim((string) ($veri['kanal'] ?? '')));
        $hesapId = (int) ($veri[$kanal.'_hesap_id'] ?? 0);
        $tutar = $this->tutarHazirla($veri['tutar'] ?? null);
        $senetTutar = number_format((float) $senet->tutar, 2, '.', '');
        if (bccomp($tutar, $senetTutar, 2) !== 0) {
            throw new IsKuraliIstisnasi('Senet ödemesi senet tutarına eşit olmalıdır.');
        }
        $kanalSecenekleri = $hareketTuru === 'tahsilat' ? ['kasa', 'banka', 'pos'] : ['kasa', 'banka'];
        if (! in_array($kanal, $kanalSecenekleri, true) || $hesapId < 1) {
            throw new IsKuraliIstisnasi('Ödeme kanalı ve ilgili hesap seçimi zorunludur.');
        }

        $islemTarihi = $this->tarihZorunlu($veri['islem_tarihi'] ?? null);
        $kapanmaSekli = (string) ($veri['kapanma_sekli'] ?? 'odendi_iade');
        if (! in_array($kapanmaSekli, ['odendi_iade', 'odendi_imha'], true)) {
            throw new IsKuraliIstisnasi('Geçersiz senet kapanış şekli.');
        }

        $idempotencyKey = $this->idempotencyKey($hareketTuru, $firmaId, [
            'senet_id' => $senet->getKey(),
            'hareket_turu' => $hareketTuru,
            'kanal' => $kanal,
            'hesap_id' => $hesapId,
            'tutar' => $tutar,
            'islem_tarihi' => $islemTarihi,
            'kapanma_sekli' => $kapanmaSekli,
            'doviz_kuru' => $veri['doviz_kuru'] ?? null,
            'hedef_tutar' => $veri['hedef_tutar'] ?? null,
        ]);

        return DB::transaction(function () use ($senet, $veri, $firmaId, $hareketTuru, $kanal, $hesapId, $tutar, $islemTarihi, $kapanmaSekli, $idempotencyKey): Senet {
            $kilitliSenet = Senet::query()->where('firma_id', $firmaId)->whereKey($senet->getKey())->lockForUpdate()->firstOrFail();
            $islemTuru = $hareketTuru === 'tahsilat' ? SenetIslemTuru::Tahsilat : SenetIslemTuru::Odeme;
            $mevcut = SenetHareketi::query()
                ->where('firma_id', $firmaId)
                ->where('senet_id', $kilitliSenet->getKey())
                ->where('islem_turu', $islemTuru->value)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($mevcut) {
                return $kilitliSenet->fresh();
            }

            $beklenenTuru = $hareketTuru === 'tahsilat' ? SenetTuru::Alinan : SenetTuru::Verilen;
            $beklenenDurum = $hareketTuru === 'tahsilat' ? SenetDurumu::Portfoyde : SenetDurumu::Verildi;
            if ($kilitliSenet->turu !== $beklenenTuru || $kilitliSenet->durum !== $beklenenDurum) {
                throw new IsKuraliIstisnasi($hareketTuru === 'tahsilat'
                    ? 'Yalnızca portföydeki alınan senet için ödeme alınabilir.'
                    : 'Yalnızca verilmiş kendi senedi için ödeme yapılabilir.');
            }

            $aktifOdeme = SenetHareketi::query()
                ->where('firma_id', $firmaId)
                ->where('senet_id', $kilitliSenet->getKey())
                ->where('islem_turu', $islemTuru->value)
                ->where('durum', SenetHareketDurumu::Aktif->value)
                ->lockForUpdate()
                ->exists();
            if ($aktifOdeme) {
                throw new IsKuraliIstisnasi($hareketTuru === 'tahsilat'
                    ? 'Bu senet için zaten aktif bir tahsilat hareketi bulunuyor.'
                    : 'Bu senet için zaten aktif bir ödeme hareketi bulunuyor.');
            }

            $cari = ($hareketTuru === 'tahsilat' ? $kilitliSenet->girisHareketi() : $kilitliSenet->cikisHareketi())
                ->where('durum', SenetHareketDurumu::Aktif->value)
                ->firstOrFail()
                ->cari;
            $kaynakParaBirimi = strtoupper((string) ($kilitliSenet->para_birimi ?: 'TRY'));
            $hesapParaBirimi = $this->hesapParaBirimi($firmaId, $kanal, $hesapId);
            $varsayilanAciklama = $hareketTuru === 'tahsilat' ? 'Senet tahsilatı: ' : 'Senet ödemesi: ';
            $aciklama = $this->bosVeyaMetin($veri['aciklama'] ?? null) ?: $varsayilanAciklama.$kilitliSenet->senet_no;
            $finans = $this->finansHareketiKaydet(
                $firmaId,
                (int) $cari->getKey(),
                $kanal,
                $hesapId,
                $tutar,
                $kaynakParaBirimi,
                $hesapParaBirimi,
                $veri,
                $islemTarihi,
                $aciklama,
                $hareketTuru,
                (int) $kilitliSenet->getKey(),
            );

            SenetHareketi::query()->create([
                'firma_id' => $firmaId,
                'senet_id' => $kilitliSenet->getKey(),
                'islem_turu' => $islemTuru,
                'cari_id' => $cari->getKey(),
                'finans_hareket_id' => $finans->getKey(),
                'islem_yapan_kullanici_id' => Auth::id(),
                'islem_tarihi' => $islemTarihi,
                'tutar' => $tutar,
                'para_birimi' => $kaynakParaBirimi,
                'idempotency_key' => $idempotencyKey,
                'durum' => SenetHareketDurumu::Aktif,
                'aciklama' => $aciklama,
            ]);

            $kilitliSenet->update([
                'durum' => $kapanmaSekli === 'odendi_imha' ? SenetDurumu::ImhaEdildi : SenetDurumu::Odendi,
                'odeme_finans_hareket_id' => $finans->getKey(),
                'kapatma_kullanici_id' => Auth::id(),
                'kapanma_tarihi' => $islemTarihi,
                'kapanma_sekli' => $kapanmaSekli,
                'kapatma_aciklama' => $this->bosVeyaMetin($veri['aciklama'] ?? null),
            ]);

            return $kilitliSenet->fresh();
        });
    }

    /**
     * Para hareketi olmadan senedi iade veya imha olarak kapatır.
     *
     * @param array<string,mixed> $veri
     */
    public function odemesizKapat(Senet $senet, array $veri): Senet
    {
        $firmaId = (int) $senet->firma_id;
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);
        $kapanmaSekli = (string) ($veri['kapanma_sekli'] ?? 'iade_edildi');
        if (! in_array($kapanmaSekli, ['iade_edildi', 'imha_edildi'], true)) {
            throw new IsKuraliIstisnasi('Geçersiz senet kapanış şekli.');
        }
        $islemTarihi = $this->tarihZorunlu($veri['islem_tarihi'] ?? null);
        $idempotencyKey = $this->idempotencyKey('kapatma', $firmaId, [
            'senet_id' => $senet->getKey(),
            'kapanma_sekli' => $kapanmaSekli,
            'islem_tarihi' => $islemTarihi,
        ]);

        return DB::transaction(function () use ($senet, $veri, $firmaId, $kapanmaSekli, $islemTarihi, $idempotencyKey): Senet {
            $kilitliSenet = Senet::query()->where('firma_id', $firmaId)->whereKey($senet->getKey())->lockForUpdate()->firstOrFail();
            $mevcut = SenetHareketi::query()
                ->where('firma_id', $firmaId)
                ->where('senet_id', $kilitliSenet->getKey())
                ->where('islem_turu', SenetIslemTuru::Kapatma->value)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($mevcut) {
                return $kilitliSenet->fresh();
            }

            if (! in_array($kilitliSenet->durum, [SenetDurumu::Portfoyde, SenetDurumu::Verildi], true)) {
                throw new IsKuraliIstisnasi('Bu senet için ödeme veya kapatma işlemi yapılamaz.');
            }

            $aktifHareket = $kilitliSenet->hareketleri()
                ->whereIn('islem_turu', [SenetIslemTuru::Giris->value, SenetIslemTuru::Cikis->value])
                ->where('durum', SenetHareketDurumu::Aktif->value)
                ->latest('id')
                ->first();
            if (! $aktifHareket) {
                throw new IsKuraliIstisnasi('Senedin aktif hareketi bulunamadı.');
            }

            SenetHareketi::query()->create([
                'firma_id' => $firmaId,
                'senet_id' => $kilitliSenet->getKey(),
                'islem_turu' => SenetIslemTuru::Kapatma,
                'cari_id' => $aktifHareket->cari_id,
                'islem_yapan_kullanici_id' => Auth::id(),
                'islem_tarihi' => $islemTarihi,
                'tutar' => $kilitliSenet->tutar,
                'para_birimi' => $kilitliSenet->para_birimi,
                'idempotency_key' => $idempotencyKey,
                'durum' => SenetHareketDurumu::Aktif,
                'aciklama' => $this->bosVeyaMetin($veri['aciklama'] ?? null),
            ]);

            $kilitliSenet->update([
                'durum' => $kapanmaSekli === 'imha_edildi' ? SenetDurumu::ImhaEdildi : SenetDurumu::IadeEdildi,
                'kapatma_kullanici_id' => Auth::id(),
                'kapanma_tarihi' => $islemTarihi,
                'kapanma_sekli' => $kapanmaSekli,
                'kapatma_aciklama' => $this->bosVeyaMetin($veri['aciklama'] ?? null),
            ]);

            return $kilitliSenet->fresh();
        });
    }

    public function iptalEt(Senet $senet): Senet
    {
        $firmaId = (int) $senet->firma_id;
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);

        return DB::transaction(function () use ($senet, $firmaId): Senet {
            $kilitliSenet = Senet::query()->where('firma_id', $firmaId)->whereKey($senet->getKey())->lockForUpdate()->firstOrFail();
            if ($kilitliSenet->durum === SenetDurumu::Iptal) {
                return $kilitliSenet;
            }

            $hareket = SenetHareketi::query()
                ->where('firma_id', $firmaId)
                ->where('senet_id', $kilitliSenet->getKey())
                ->where('durum', SenetHareketDurumu::Aktif->value)
                ->latest('id')
                ->lockForUpdate()
                ->firstOrFail();

            if ($hareket->finans_hareket_id) {
                $this->finansHareketServisi->tersKayitOlustur(
                    $hareket->finansHareketi,
                    'Senet hareketi iptali: #'.$hareket->getKey(),
                );
            }
            $hareket->update(['durum' => SenetHareketDurumu::Iptal]);

            $aktifCikis = SenetHareketi::query()
                ->where('firma_id', $firmaId)
                ->where('senet_id', $kilitliSenet->getKey())
                ->where('islem_turu', SenetIslemTuru::Cikis->value)
                ->where('durum', SenetHareketDurumu::Aktif->value)
                ->exists();
            $hareketTuru = $hareket->islem_turu instanceof SenetIslemTuru
                ? $hareket->islem_turu->value
                : (string) $hareket->islem_turu;
            $odemeHareketiMi = in_array($hareketTuru, [SenetIslemTuru::Tahsilat->value, SenetIslemTuru::Odeme->value], true);
            $yeniDurum = $odemeHareketiMi
                ? ($kilitliSenet->turu === SenetTuru::Alinan ? SenetDurumu::Portfoyde : SenetDurumu::Verildi)
                : ($hareket->islem_turu === SenetIslemTuru::Cikis && $kilitliSenet->turu === SenetTuru::Alinan && ! $aktifCikis
                    ? SenetDurumu::Portfoyde
                    : SenetDurumu::Iptal);

            $kilitliSenet->update([
                'durum' => $yeniDurum,
                'odeme_finans_hareket_id' => $odemeHareketiMi ? null : $kilitliSenet->odeme_finans_hareket_id,
                'kapatma_kullanici_id' => null,
                'kapanma_tarihi' => null,
                'kapanma_sekli' => null,
                'kapatma_aciklama' => null,
            ]);

            return $kilitliSenet->fresh();
        });
    }

    /**
     * Aktif senet tahsilat/ödeme hareketini iptal edip yeni belge hareketi
     * oluşturur. Yeni finans kaydı eski finansla düzeltme ilişkisi taşır.
     *
     * @param array<string,mixed> $veri
     */
    public function hareketIptalEtVeDuzelt(Senet $senet, array $veri): Senet
    {
        $aktif = SenetHareketi::query()
            ->where('firma_id', (int) $senet->firma_id)
            ->where('senet_id', (int) $senet->getKey())
            ->where('durum', SenetHareketDurumu::Aktif->value)
            ->latest('id')
            ->firstOrFail();
        $eskiFinansId = (int) ($aktif->finans_hareket_id ?? 0);

        $this->iptalEt($senet);
        $yenilenmis = $senet->fresh();
        $yeni = ($aktif->islem_turu instanceof SenetIslemTuru ? $aktif->islem_turu : SenetIslemTuru::tryFrom((string) $aktif->islem_turu)) === SenetIslemTuru::Tahsilat
            ? $this->tahsilatEkle($yenilenmis, $veri)
            : $this->odemeYap($yenilenmis, $veri);

        $yeniHareket = SenetHareketi::query()
            ->where('senet_id', (int) $senet->getKey())
            ->where('durum', SenetHareketDurumu::Aktif->value)
            ->latest('id')
            ->first();
        if ($eskiFinansId > 0 && $yeniHareket?->finans_hareket_id) {
            FinansHareketi::query()->whereKey((int) $yeniHareket->finans_hareket_id)->update(['duzeltme_kaynagi_id' => $eskiFinansId]);
        }

        return $yeni->fresh();
    }

    /** @return array{finans: \App\Models\Muhasebe\FinansHareketi} */
    private function finansHareketiKaydet(
        int $firmaId,
        int $cariId,
        string $kanal,
        int $hesapId,
        string $cariTutar,
        string $cariParaBirimi,
        string $hesapParaBirimi,
        array $veri,
        string $tarih,
        string $aciklama,
        string $hareketTuru,
        int $senetId,
    ): \App\Models\Muhasebe\FinansHareketi {
        $referansTuru = 'senet';
        if ($cariParaBirimi === $hesapParaBirimi) {
            $sonuc = $hareketTuru === 'tahsilat'
                ? match ($kanal) {
                    'kasa' => $this->finansHareketServisi->tahsilatKasadanKaydet($firmaId, $cariId, $hesapId, $cariTutar, $cariParaBirimi, $tarih, $aciklama, $referansTuru, $senetId),
                    'banka' => $this->finansHareketServisi->tahsilatBankadanKaydet($firmaId, $cariId, $hesapId, $cariTutar, $cariParaBirimi, $tarih, $aciklama, $referansTuru, $senetId),
                    'pos' => $this->finansHareketServisi->tahsilatPosKaydet($firmaId, $cariId, $hesapId, $cariTutar, $cariParaBirimi, $tarih, $aciklama, $referansTuru, $senetId),
                    default => throw new IsKuraliIstisnasi('Geçersiz tahsilat kanalı.'),
                }
                : match ($kanal) {
                    'kasa' => $this->finansHareketServisi->odemeKasadanKaydet($firmaId, $cariId, $hesapId, $cariTutar, $cariParaBirimi, $tarih, $aciklama, $referansTuru, $senetId),
                    'banka' => $this->finansHareketServisi->odemeBankadanKaydet($firmaId, $cariId, $hesapId, $cariTutar, $cariParaBirimi, $tarih, $aciklama, $referansTuru, $senetId),
                    default => throw new IsKuraliIstisnasi('Kendi senedi için ödeme kanalı yalnızca kasa veya bankadır.'),
                };

            return $sonuc['finans'];
        }

        $kur = (float) ($veri['doviz_kuru'] ?? 0);
        if ($kur <= 0) {
            throw new IsKuraliIstisnasi('Farklı para birimlerinde ödeme için kur bilgisi zorunludur.');
        }
        $hedefTutar = (float) ($veri['hedef_tutar'] ?? 0);
        if ($hedefTutar <= 0) {
            $hedefTutar = $cariParaBirimi === 'TRY'
                ? (float) bcdiv($cariTutar, (string) $kur, 2)
                : (float) bcmul($cariTutar, (string) $kur, 2);
        }
        if ($hareketTuru === 'tahsilat') {
            return $this->finansHareketServisi->tahsilatKurIleKaydet($firmaId, $cariId, $kanal, $hesapId, $cariTutar, $cariParaBirimi, number_format($hedefTutar, 2, '.', ''), $hesapParaBirimi, number_format($kur, 8, '.', ''), $tarih, $aciklama, $referansTuru, $senetId)['finans'];
        }

        return $this->finansHareketServisi->odemeKurIleKaydet($firmaId, $cariId, $kanal, $hesapId, $cariTutar, $cariParaBirimi, number_format($hedefTutar, 2, '.', ''), $hesapParaBirimi, number_format($kur, 8, '.', ''), $tarih, $aciklama, $referansTuru, $senetId)['finans'];
    }

    private function hesapParaBirimi(int $firmaId, string $kanal, int $hesapId): string
    {
        $model = match ($kanal) {
            'kasa' => KasaHesabi::class,
            'banka' => BankaHesabi::class,
            'pos' => PosHesabi::class,
            default => throw new IsKuraliIstisnasi('Geçersiz ödeme kanalı.'),
        };
        $hesap = $model::query()->where('firma_id', $firmaId)->whereKey($hesapId)->first();
        if (! $hesap) {
            throw new IsKuraliIstisnasi('Seçilen hesap aktif firmaya ait değil.');
        }

        return strtoupper((string) ($hesap->para_birimi ?: 'TRY'));
    }

    private function cariyiDogrula(int $firmaId, int $cariId, string $paraBirimi): Cari
    {
        $cari = Cari::query()->whereKey($cariId)->where('firma_id', $firmaId)->firstOrFail();
        return $cari;
    }

    private function tarihZorunlu(mixed $tarih): string
    {
        $tarih = trim((string) $tarih);
        if ($tarih === '') {
            throw new IsKuraliIstisnasi('İşlem tarihi zorunludur.');
        }

        return $tarih;
    }

    private function tutarHazirla(mixed $tutar): string
    {
        $tutar = trim((string) $tutar);
        if (str_contains($tutar, ',') && str_contains($tutar, '.')) {
            $tutar = str_replace('.', '', $tutar);
        }
        $tutar = str_replace(',', '.', $tutar);
        if ($tutar === '' || ! is_numeric($tutar) || bccomp($tutar, '0', 2) <= 0) {
            throw new IsKuraliIstisnasi('Senet tutarı sıfırdan büyük olmalıdır.');
        }

        return number_format((float) $tutar, 2, '.', '');
    }

    private function bosVeyaMetin(mixed $deger): ?string
    {
        $deger = trim((string) $deger);

        return $deger === '' ? null : $deger;
    }

    private function gorselYolunuDogrula(int $firmaId, mixed $yol): ?string
    {
        $yol = trim((string) $yol);
        if ($yol === '') {
            return null;
        }
        $beklenenKlasor = 'muhasebe/senetler/'.$firmaId.'/';
        if (! str_starts_with($yol, $beklenenKlasor) || str_contains($yol, '..')) {
            throw new IsKuraliIstisnasi('Senet görseli aktif firmaya ait güvenli klasörde olmalıdır.');
        }
        if (! Storage::disk('public')->exists($yol)) {
            throw new IsKuraliIstisnasi('Senet görseli bulunamadı; lütfen görseli yeniden yükleyin.');
        }

        return $yol;
    }

    /** @param array<string,mixed> $parcalar */
    private function idempotencyKey(string $tur, int $firmaId, array $parcalar): string
    {
        ksort($parcalar);

        return hash('sha256', 'senet:'.$tur.':'.$firmaId.':'.json_encode($parcalar, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
